<?php

namespace App\Http\Controllers\API;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreSalaryAssignmentRequest;
use App\Http\Resources\SalaryAssignmentResource;
use App\Models\Staff;
use App\Services\Payroll\SalaryAssignmentService;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * An employee's salary and its revision history.
 *
 * Lives under the staff resource because a salary belongs to a person, not to
 * a payroll run.
 */
class SalaryAssignmentController extends Controller
{
    public function __construct(private readonly SalaryAssignmentService $service) {}

    public function index(Request $request, Staff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        return SalaryAssignmentResource::collection($this->service->history($staff))->response();
    }

    public function current(Request $request, Staff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        $assignment = $this->service->current($staff);

        return response()->json([
            'data' => $assignment === null
                ? null
                : new SalaryAssignmentResource($assignment->load(['structure', 'componentValues.component'])),
        ]);
    }

    public function store(StoreSalaryAssignmentRequest $request, Staff $staff): JsonResponse
    {
        abort_unless($staff->owner_id === $request->user()->workspaceOwnerId(), 403);

        $assignment = $this->service->assign(
            $staff,
            $request->user(),
            [...$request->validated(), 'currency_code' => $request->currencyCode()],
            $this->componentValues($request),
        );

        return (new SalaryAssignmentResource($assignment))->response()->setStatusCode(201);
    }

    public function end(Request $request, Staff $staff): JsonResponse
    {
        abort_unless($staff->owner_id === $request->user()->workspaceOwnerId(), 403);

        $validated = $request->validate(['effective_to' => ['required', 'date']]);
        $assignment = $this->service->current($staff);

        abort_if($assignment === null, 404, 'This employee has no active salary.');

        return response()->json([
            'data' => new SalaryAssignmentResource($this->service->end($assignment, Carbon::parse($validated['effective_to']))),
        ]);
    }

    /**
     * Salary is personal data: without payroll.view-all an employee may see
     * only their own.
     */
    private function authorizeStaff(Request $request, Staff $staff): void
    {
        abort_unless($staff->owner_id === $request->user()->workspaceOwnerId(), 403);
        abort_unless(RecordScope::allows($request->user(), Module::Payroll, $staff->id), 403);
    }

    /** @return array<int, float> */
    private function componentValues(StoreSalaryAssignmentRequest $request): array
    {
        $values = [];

        foreach ((array) $request->input('component_values', []) as $componentId => $value) {
            $values[(int) $componentId] = (float) $value;
        }

        return $values;
    }
}
