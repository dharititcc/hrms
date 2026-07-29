<?php

namespace App\Services\Payroll;

use App\Enums\PayrollCountry;
use App\Enums\PayrollRunStatus;
use App\Models\EmployeeSalaryAssignment;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\SalarySlipLine;
use App\Models\Employee;
use App\Models\User;
use App\Support\Payroll\SalaryBreakdown;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Produces a payroll run and its salary slips.
 *
 * Slips are calculated from the assignment in force at the end of the period,
 * then written as frozen figures with their own breakdown lines. Nothing reads
 * back through the structure afterwards, so correcting a structure next month
 * cannot silently rewrite a slip that has already been issued.
 */
class PayrollGenerationService
{
    public function __construct(private readonly SalaryCalculator $calculator) {}

    /**
     * @param  array<int, array<string, float>>  $manualAmounts  employee id => component code => amount
     */
    public function generate(User $author, array $attributes, array $manualAmounts = []): PayrollRun
    {
        $country = $attributes['country'] instanceof PayrollCountry
            ? $attributes['country']
            : PayrollCountry::from($attributes['country']);

        $periodStart = Carbon::parse($attributes['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($attributes['period_end'])->startOfDay();

        $run = PayrollRun::create([
            'owner_id' => $author->workspaceOwnerId(),
            'title' => $attributes['title'],
            'country' => $country,
            'currency_code' => $country->currencyCode(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'pay_date' => $attributes['pay_date'] ?? null,
            'status' => PayrollRunStatus::Draft,
            'generated_by' => $author->id,
            'notes' => $attributes['notes'] ?? null,
        ]);

        return $this->build($run, $manualAmounts);
    }

    /**
     * Recalculates a draft run from current salary data.
     *
     * Refused once approved: the figures are committed at that point and people
     * may already have been told what they are being paid.
     *
     * @param  array<int, array<string, float>>  $manualAmounts
     */
    public function regenerate(PayrollRun $run, array $manualAmounts = []): PayrollRun
    {
        if ($run->status->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'This payroll has been approved and can no longer be regenerated.',
            ]);
        }

        DB::transaction(fn () => $run->slips()->delete());

        return $this->build($run->refresh(), $manualAmounts);
    }

    /** @param array<int, array<string, float>> $manualAmounts */
    private function build(PayrollRun $run, array $manualAmounts): PayrollRun
    {
        $assignments = $this->assignmentsFor($run);

        DB::transaction(function () use ($run, $assignments, $manualAmounts): void {
            foreach ($assignments as $assignment) {
                $breakdown = $this->calculator->calculate(
                    $assignment,
                    $manualAmounts[$assignment->staff_id] ?? [],
                );

                $this->writeSlip($run, $assignment, $breakdown);
            }

            $this->rollUpTotals($run);
        });

        return $run->refresh()->load(['slips.employee', 'slips.lines']);
    }

    /**
     * One assignment per employee: the one in force at the end of the period.
     *
     * Using the period end means a mid-month rise is reflected in that month's
     * pay. Proration is not modelled; a part-month change pays at the new rate
     * for the whole period.
     *
     * @return list<EmployeeSalaryAssignment>
     */
    private function assignmentsFor(PayrollRun $run): array
    {
        $employeeIds = Employee::query()
            ->where('owner_id', $run->owner_id)
            ->where('status', 'active')
            ->pluck('id');

        return EmployeeSalaryAssignment::query()
            ->where('owner_id', $run->owner_id)
            ->whereIn('staff_id', $employeeIds)
            ->where('country', $run->country)
            ->effectiveOn($run->period_end)
            ->orderBy('staff_id')
            ->orderByDesc('effective_from')
            ->get()
            // Guards against an employee somehow having two rows covering the
            // same day: the later start wins.
            ->unique('staff_id')
            ->values()
            ->all();
    }

    private function writeSlip(PayrollRun $run, EmployeeSalaryAssignment $assignment, SalaryBreakdown $breakdown): void
    {
        $slip = SalarySlip::create([
            'owner_id' => $run->owner_id,
            'payroll_run_id' => $run->id,
            'staff_id' => $assignment->staff_id,
            'employee_salary_assignment_id' => $assignment->id,
            'slip_number' => sprintf('PS-%d-%d', $run->id, $assignment->staff_id),
            'country' => $run->country,
            'currency_code' => $breakdown->currencyCode,
            'basic_salary' => $breakdown->basicSalary,
            'total_earnings' => $breakdown->totalEarnings(),
            'total_deductions' => $breakdown->totalDeductions(),
            'employer_contributions' => $breakdown->totalEmployerContributions(),
            'gross_salary' => $breakdown->grossSalary(),
            'net_salary' => $breakdown->netSalary(),
            'status' => 'draft',
        ]);

        foreach ($breakdown->lines as $line) {
            SalarySlipLine::create(['salary_slip_id' => $slip->id, ...$line->toArray()]);
        }
    }

    private function rollUpTotals(PayrollRun $run): void
    {
        $totals = $run->slips()
            ->selectRaw('count(*) as slips, coalesce(sum(total_earnings),0) as earnings, coalesce(sum(total_deductions),0) as deductions, coalesce(sum(net_salary),0) as net')
            ->first();

        $run->update([
            'slip_count' => (int) $totals->slips,
            // Gross across the run is basic plus earnings, so it is summed from
            // the slips rather than recomputed.
            'total_earnings' => (float) $totals->earnings,
            'total_deductions' => (float) $totals->deductions,
            'total_net' => (float) $totals->net,
        ]);
    }
}
