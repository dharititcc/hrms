<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunStatus;
use App\Enums\SalarySlipStatus;
use App\Models\PayrollRun;
use App\Models\SalaryPayment;
use App\Models\SalarySlip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a payroll run from draft to paid.
 *
 *   draft → pending approval → approved → paid
 *
 * Approval is the point of no return: the figures are committed, everyone can
 * be told what they are being paid, and the run can no longer be recalculated
 * or deleted. Everything before it is reversible, nothing after it is.
 *
 * Payment is recorded per slip rather than per run, because a failed transfer
 * leaves one employee unpaid while the rest are settled. The run reaches "paid"
 * only when no slip has anything outstanding.
 */
class PayrollWorkflowService
{
    /** Hands a draft to whoever approves it. */
    public function submit(PayrollRun $run): PayrollRun
    {
        $this->assertStatus($run, [PayrollRunStatus::Draft], 'Only a draft can be submitted for approval.');
        $this->assertHasSlips($run);

        $run->update(['status' => PayrollRunStatus::PendingApproval]);

        return $run->refresh();
    }

    /**
     * Commits the run.
     *
     * Deliberately does not refuse a run whose manual lines are still zero. A
     * zero income tax is legitimate for someone under the threshold, so the
     * decision belongs to the person approving; the interface warns them.
     */
    public function approve(PayrollRun $run, User $approver): PayrollRun
    {
        $this->assertStatus(
            $run,
            [PayrollRunStatus::Draft, PayrollRunStatus::PendingApproval],
            'This payroll has already been approved.',
        );
        $this->assertHasSlips($run);

        DB::transaction(function () use ($run, $approver): void {
            $run->update([
                'status' => PayrollRunStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $run->slips()->update(['status' => SalarySlipStatus::Approved]);
        });

        return $run->refresh();
    }

    /**
     * Abandons a run.
     *
     * Allowed after approval, because the alternative to correcting a mistake
     * is paying it. Refused once money has been recorded against it: that
     * happened, and a status change cannot unhappen it.
     */
    public function cancel(PayrollRun $run): PayrollRun
    {
        $this->assertStatus(
            $run,
            [PayrollRunStatus::Draft, PayrollRunStatus::PendingApproval, PayrollRunStatus::Approved],
            'This payroll has already been cancelled.',
        );

        $paid = $run->slips()->where('paid_amount', '>', 0)->count();

        if ($paid > 0) {
            throw ValidationException::withMessages([
                'status' => "This payroll cannot be cancelled: {$paid} payslip(s) already have a payment recorded against them.",
            ]);
        }

        DB::transaction(function () use ($run): void {
            $run->update(['status' => PayrollRunStatus::Cancelled]);
            $run->slips()->update(['status' => SalarySlipStatus::Cancelled]);
        });

        return $run->refresh();
    }

    /**
     * Records money paid against one payslip.
     *
     * @param  array{amount: float, paid_at?: string|null, method?: string, reference?: string|null, note?: string|null}  $attributes
     */
    public function recordPayment(SalarySlip $slip, User $recorder, array $attributes): SalaryPayment
    {
        $run = $slip->run;

        // Approval is what makes a figure safe to pay. Paying before it would
        // make the approval step decorative.
        if ($run === null || ! in_array($run->status, [PayrollRunStatus::Approved, PayrollRunStatus::Paid], strict: true)) {
            throw ValidationException::withMessages([
                'amount' => 'This payroll has not been approved yet, so payments cannot be recorded against it.',
            ]);
        }

        $amount = round((float) $attributes['amount'], 2);
        $outstanding = $slip->outstanding();

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'A payment must be greater than zero.']);
        }

        // Tolerance absorbs the half-penny that decimal rounding can leave
        // behind; anything larger is a genuine overpayment.
        if ($amount > $outstanding + 0.001) {
            throw ValidationException::withMessages([
                'amount' => "That is more than the {$slip->currency_code} {$outstanding} still outstanding on this payslip.",
            ]);
        }

        return DB::transaction(function () use ($slip, $recorder, $attributes, $amount, $run): SalaryPayment {
            $payment = SalaryPayment::create([
                'owner_id' => $slip->owner_id,
                'salary_slip_id' => $slip->id,
                'amount' => $amount,
                'currency_code' => $slip->currency_code,
                'paid_at' => isset($attributes['paid_at']) ? Carbon::parse($attributes['paid_at']) : now(),
                'method' => $attributes['method'] ?? 'bank_transfer',
                'reference' => $attributes['reference'] ?? null,
                'note' => $attributes['note'] ?? null,
                'recorded_by' => $recorder->id,
            ]);

            $this->rollUpSlip($slip);
            $this->rollUpRun($run);

            return $payment;
        });
    }

    /** Removes a payment recorded in error, and restates what it affected. */
    public function reversePayment(SalaryPayment $payment): void
    {
        $slip = $payment->slip;

        DB::transaction(function () use ($payment, $slip): void {
            $payment->delete();

            if ($slip !== null) {
                $this->rollUpSlip($slip);

                if ($slip->run !== null) {
                    $this->rollUpRun($slip->run);
                }
            }
        });
    }

    /** Recomputes paid_amount from the payments themselves, never by adding. */
    private function rollUpSlip(SalarySlip $slip): void
    {
        $paid = round((float) $slip->payments()->sum('amount'), 2);

        $slip->update([
            'paid_amount' => $paid,
            'status' => SalarySlipStatus::forAmounts(round((float) $slip->net_salary, 2), $paid),
        ]);
    }

    /**
     * A run is paid only when nothing is outstanding anywhere in it. Recorded
     * in reverse too: reversing a payment takes the run back to approved.
     */
    private function rollUpRun(PayrollRun $run): void
    {
        $unsettled = $run->slips()
            ->whereColumn('paid_amount', '<', 'net_salary')
            ->count();

        $status = $unsettled === 0 ? PayrollRunStatus::Paid : PayrollRunStatus::Approved;

        if ($run->status !== $status) {
            $run->update(['status' => $status]);
        }
    }

    /** @param list<PayrollRunStatus> $allowed */
    private function assertStatus(PayrollRun $run, array $allowed, string $message): void
    {
        if (! in_array($run->status, $allowed, strict: true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function assertHasSlips(PayrollRun $run): void
    {
        if ($run->slips()->count() === 0) {
            throw ValidationException::withMessages([
                'status' => 'This payroll has no payslips. Generate it against employees who have a salary set first.',
            ]);
        }
    }
}
