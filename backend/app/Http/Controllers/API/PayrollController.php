<?php

namespace App\Http\Controllers\API;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollRecordResource;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    /**
     * Payroll records. Without payroll.view-all this returns only the caller's
     * own payslips, which is how an employee can see their pay without seeing
     * the workspace's.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PayrollRecord::query()
            ->with(['staff', 'period'])
            ->where('owner_id', $request->user()->workspaceOwnerId());

        RecordScope::apply($query, $request->user(), Module::Payroll);

        return PayrollRecordResource::collection($query->latest()->paginate(20))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'staff_id' => ['required', 'exists:staff,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Confirms the staff member belongs to this workspace.
        $request->user()->workspaceStaff()->findOrFail($validated['staff_id']);

        $ownerId = $request->user()->workspaceOwnerId();
        $allowances = $validated['allowances'] ?? 0;
        $deductions = $validated['deductions'] ?? 0;

        $period = PayrollPeriod::create([
            'owner_id' => $ownerId,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        $record = PayrollRecord::create([
            'owner_id' => $ownerId,
            'payroll_period_id' => $period->id,
            'staff_id' => $validated['staff_id'],
            'basic_salary' => $validated['basic_salary'],
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $validated['basic_salary'] + $allowances - $deductions,
        ]);

        return response()->json(new PayrollRecordResource($record->load(['staff', 'period'])), 201);
    }
}
