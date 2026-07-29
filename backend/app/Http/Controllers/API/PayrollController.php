<?php

namespace App\Http\Controllers\API;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalarySlipResource;
use App\Models\SalarySlip;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Salary slips produced by payroll runs.
 *
 * Read-only. Slips are created by generating a payroll run from salary
 * assignments, not by posting figures directly — that was how the retired
 * payroll_records table worked, and it left no salary history behind.
 */
class PayrollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['nullable', 'integer'],
            'payroll_run_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SalarySlip::query()
            ->with(['staff', 'run'])
            ->where('owner_id', $request->user()->workspaceOwnerId());

        // Without payroll.view-all this returns only the caller's own payslips.
        RecordScope::apply($query, $request->user(), Module::Payroll);

        $query->when($validated['staff_id'] ?? null, fn ($q, $id) => $q->where('staff_id', $id))
            ->when($validated['payroll_run_id'] ?? null, fn ($q, $id) => $q->where('payroll_run_id', $id));

        return SalarySlipResource::collection(
            $query->latest('id')->paginate((int) ($validated['per_page'] ?? 20)),
        )->response();
    }

    public function show(Request $request, SalarySlip $slip): SalarySlipResource
    {
        abort_unless($slip->owner_id === $request->user()->workspaceOwnerId(), 403);
        abort_unless(RecordScope::allows($request->user(), Module::Payroll, $slip->staff_id), 403);

        return new SalarySlipResource($slip->load(['staff', 'run', 'lines']));
    }
}
