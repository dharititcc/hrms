<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\GeneratePayrollRequest;
use App\Http\Resources\PayrollRunResource;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollGenerationService;
use App\Services\Payroll\PayrollWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollGenerationService $service,
        private readonly PayrollWorkflowService $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $runs = PayrollRun::query()
            ->where('owner_id', $request->user()->workspaceOwnerId())
            ->latest('period_start')
            ->paginate(20);

        return PayrollRunResource::collection($runs)->response();
    }

    public function show(Request $request, PayrollRun $run): PayrollRunResource
    {
        $this->authorizeRun($request, $run);

        return new PayrollRunResource($run->load(['slips.employee', 'slips.lines', 'slips.payments']));
    }

    public function store(GeneratePayrollRequest $request): JsonResponse
    {
        $run = $this->service->generate($request->user(), $request->validated(), $request->manualAmounts());

        return (new PayrollRunResource($run))->response()->setStatusCode(201);
    }

    /** Recalculates a draft run from current salary data. */
    public function regenerate(GeneratePayrollRequest $request, PayrollRun $run): PayrollRunResource
    {
        $this->authorizeRun($request, $run);

        /*
        | isEditable rather than !isLocked: a cancelled run is neither, and
        | recalculating one would quietly revive it as working figures while it
        | still reads as cancelled.
        */
        abort_unless($run->status->isEditable(), 422, 'Only a draft payroll can be recalculated.');

        return new PayrollRunResource($this->service->regenerate($run, $request->manualAmounts()));
    }

    /** Hands the draft to whoever approves it. */
    public function submit(Request $request, PayrollRun $run): PayrollRunResource
    {
        $this->authorizeRun($request, $run);

        return new PayrollRunResource($this->workflow->submit($run));
    }

    /**
     * Commits the figures. Past this point the run cannot be recalculated or
     * deleted, and payments may be recorded against its payslips.
     */
    public function approve(Request $request, PayrollRun $run): PayrollRunResource
    {
        $this->authorizeRun($request, $run);

        return new PayrollRunResource($this->workflow->approve($run, $request->user()));
    }

    public function cancel(Request $request, PayrollRun $run): PayrollRunResource
    {
        $this->authorizeRun($request, $run);

        return new PayrollRunResource($this->workflow->cancel($run));
    }

    public function destroy(Request $request, PayrollRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        // An approved run is a record of what people were told they would be
        // paid, so it is not deletable.
        abort_if($run->status->isLocked(), 422, 'An approved payroll cannot be deleted.');

        $run->delete();

        return response()->json(['message' => 'Payroll run deleted.']);
    }

    private function authorizeRun(Request $request, PayrollRun $run): void
    {
        abort_unless($run->owner_id === $request->user()->workspaceOwnerId(), 403);
    }
}
