<?php

namespace App\Services\Payroll;

use App\Enums\SalaryAssignmentStatus;
use App\Models\EmployeeSalaryAssignment;
use App\Models\EmployeeSalaryComponentValue;
use App\Models\SalaryComponent;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns and revises salaries.
 *
 * A revision never edits the existing row. The current assignment is closed
 * the day before the new one starts and marked superseded, so the history
 * remains a continuous, gap-free record and any payslip already issued can
 * still be explained by the assignment that was in force at the time.
 */
class SalaryAssignmentService
{
    /** Every assignment for one employee, newest first. */
    public function history(Staff $staff): Collection
    {
        return EmployeeSalaryAssignment::query()
            ->where('staff_id', $staff->id)
            ->with(['structure', 'componentValues.component'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /** The assignment in force on a date, or null if the employee had none. */
    public function effectiveOn(Staff $staff, Carbon $date): ?EmployeeSalaryAssignment
    {
        return EmployeeSalaryAssignment::query()
            ->where('staff_id', $staff->id)
            ->whereIn('status', [SalaryAssignmentStatus::Active, SalaryAssignmentStatus::Superseded])
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->first();
    }

    public function current(Staff $staff): ?EmployeeSalaryAssignment
    {
        return EmployeeSalaryAssignment::query()
            ->where('staff_id', $staff->id)
            ->active()
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Creates the employee's salary, or a revision of it.
     *
     * @param  array<int, float>  $componentValues  component id => per-employee value
     */
    public function assign(Staff $staff, User $author, array $attributes, array $componentValues = []): EmployeeSalaryAssignment
    {
        $effectiveFrom = Carbon::parse($attributes['effective_from'])->startOfDay();
        $current = $this->current($staff);

        if ($current !== null && $effectiveFrom->lessThanOrEqualTo($current->effective_from)) {
            // Allowing this would leave two assignments claiming the same days
            // and make historic payslips ambiguous.
            throw ValidationException::withMessages([
                'effective_from' => 'A revision must start after the current salary began, on '
                    .$current->effective_from->toDateString().'.',
            ]);
        }

        return DB::transaction(function () use ($staff, $author, $attributes, $componentValues, $effectiveFrom, $current): EmployeeSalaryAssignment {
            if ($current !== null) {
                $current->update([
                    // Closed the day before the revision starts, leaving no gap
                    // and no overlap.
                    'effective_to' => $effectiveFrom->copy()->subDay()->toDateString(),
                    'status' => SalaryAssignmentStatus::Superseded,
                ]);
            }

            $assignment = EmployeeSalaryAssignment::create([
                'owner_id' => $staff->owner_id,
                'staff_id' => $staff->id,
                'salary_structure_id' => $attributes['salary_structure_id'] ?? null,
                'basic_salary' => $attributes['basic_salary'],
                'currency_code' => $attributes['currency_code'],
                'country' => $attributes['country'],
                'effective_from' => $effectiveFrom->toDateString(),
                'status' => SalaryAssignmentStatus::Active,
                'revision_reason' => $attributes['revision_reason'] ?? null,
                'supersedes_id' => $current?->id,
                'created_by' => $author->id,
            ]);

            $this->syncComponentValues($assignment, $componentValues);

            return $assignment->load(['structure', 'componentValues.component']);
        });
    }

    /** Ends a salary, for someone leaving. */
    public function end(EmployeeSalaryAssignment $assignment, Carbon $endsOn): EmployeeSalaryAssignment
    {
        if ($endsOn->lessThan($assignment->effective_from)) {
            throw ValidationException::withMessages([
                'effective_to' => 'A salary cannot end before it started.',
            ]);
        }

        $assignment->update([
            'effective_to' => $endsOn->toDateString(),
            'status' => SalaryAssignmentStatus::Ended,
        ]);

        return $assignment->refresh();
    }

    /** @param array<int, float> $values component id => value */
    private function syncComponentValues(EmployeeSalaryAssignment $assignment, array $values): void
    {
        if ($values === []) {
            return;
        }

        // Only components belonging to this workspace may be overridden.
        $allowed = SalaryComponent::query()
            ->where('owner_id', $assignment->owner_id)
            ->whereIn('id', array_keys($values))
            ->pluck('id')
            ->all();

        foreach ($allowed as $componentId) {
            EmployeeSalaryComponentValue::updateOrCreate(
                ['employee_salary_assignment_id' => $assignment->id, 'salary_component_id' => $componentId],
                ['value' => $values[$componentId]],
            );
        }
    }
}
