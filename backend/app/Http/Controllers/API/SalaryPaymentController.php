<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\RecordSalaryPaymentRequest;
use App\Http\Resources\SalaryPaymentResource;
use App\Models\SalaryPayment;
use App\Models\SalarySlip;
use App\Services\Payroll\PayrollWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Money actually paid against a payslip.
 *
 * Separate from the slip because payment is not a flag: it can be partial,
 * arrive in instalments, be made by different methods, and occasionally be
 * recorded in error and need reversing.
 */
class SalaryPaymentController extends Controller
{
    public function __construct(private readonly PayrollWorkflowService $workflow) {}

    public function index(Request $request, SalarySlip $slip): JsonResponse
    {
        $this->authorizeSlip($request, $slip);

        $payments = $slip->payments()->with('recorder')->latest('paid_at')->get();

        return SalaryPaymentResource::collection($payments)
            ->additional(['meta' => [
                'net_salary' => $slip->net_salary,
                'paid_amount' => $slip->paid_amount,
                'outstanding' => $slip->outstanding(),
                'methods' => RecordSalaryPaymentRequest::METHODS,
            ]])
            ->response();
    }

    public function store(RecordSalaryPaymentRequest $request, SalarySlip $slip): JsonResponse
    {
        $this->authorizeSlip($request, $slip);

        $payment = $this->workflow->recordPayment($slip, $request->user(), $request->validated());

        return (new SalaryPaymentResource($payment))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, SalaryPayment $payment): JsonResponse
    {
        abort_unless($payment->owner_id === $request->user()->workspaceOwnerId(), 403);

        // Reversing restates the slip and its run, so a run that had reached
        // "paid" drops back to "approved" rather than silently staying settled.
        $this->workflow->reversePayment($payment);

        return response()->json(['message' => 'Payment reversed.']);
    }

    private function authorizeSlip(Request $request, SalarySlip $slip): void
    {
        abort_unless($slip->owner_id === $request->user()->workspaceOwnerId(), 403);
    }
}
