<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Notifications\PayslipIssuedNotification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;

/**
 * Sends payslips out.
 *
 * Only from an approved run: a draft's figures can still change, and a payslip
 * in somebody's inbox is not something that can be taken back.
 */
class PayslipDeliveryService
{
    /**
     * @return array{sent: int, skipped_without_account: int, skipped_already_sent: int}
     */
    public function sendRun(PayrollRun $run, bool $resend = false): array
    {
        $this->assertApproved($run);

        $slips = $run->slips()->with(['employee.user', 'run'])->get();

        $sent = 0;
        $withoutAccount = 0;
        $alreadySent = 0;

        foreach ($slips as $slip) {
            if (! $resend && $slip->emailed_at !== null) {
                $alreadySent++;

                continue;
            }

            if ($this->send($slip) === false) {
                $withoutAccount++;

                continue;
            }

            $sent++;
        }

        return [
            'sent' => $sent,
            'skipped_without_account' => $withoutAccount,
            'skipped_already_sent' => $alreadySent,
        ];
    }

    /**
     * Sends one payslip.
     *
     * Returns false when there is nobody to send it to. A employee with no
     * account still has an email address, but sending there would mean salary
     * figures going to an address nobody has proved they control — an invite
     * has to be accepted first.
     */
    public function send(SalarySlip $slip): bool
    {
        $this->assertApproved($slip->run);

        $user = $slip->employee?->user;

        if ($user === null) {
            return false;
        }

        NotificationFacade::send($user, new PayslipIssuedNotification($slip));

        $slip->update(['emailed_at' => now()]);

        return true;
    }

    private function assertApproved(?PayrollRun $run): void
    {
        $approved = $run !== null && in_array(
            $run->status,
            [PayrollRunStatus::Approved, PayrollRunStatus::Paid],
            strict: true,
        );

        if (! $approved) {
            throw ValidationException::withMessages([
                'status' => 'This payroll has not been approved yet, so its payslips cannot be sent.',
            ]);
        }
    }
}
