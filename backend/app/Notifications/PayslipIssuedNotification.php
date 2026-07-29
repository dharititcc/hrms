<?php

namespace App\Notifications;

use App\Models\SalarySlip;
use App\Services\Payroll\PayslipPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an employee their payslip is ready.
 *
 * Whether the PDF travels with the email is a config decision, not a code one:
 * attaching is convenient and expected, but it puts salary figures in an inbox
 * and through every relay in between. See config/payslip.php.
 */
class PayslipIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SalarySlip $slip) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $slip = $this->slip->loadMissing(['run', 'employee']);
        $period = $slip->run?->title ?? 'the latest period';
        $amount = $slip->country->currencySymbol().number_format((float) $slip->net_salary, 2);

        $message = (new MailMessage)
            ->subject("Your payslip for {$period}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your payslip for {$period} is ready.")
            ->line("Net pay: {$amount} ({$slip->currency_code}).");

        if (config('payslip.attach_pdf')) {
            $pdf = app(PayslipPdfService::class);

            $message->attachData($pdf->render($slip), $pdf->filename($slip), ['mime' => 'application/pdf']);
        } else {
            // Without the attachment the link is the only way to it, so the
            // email says plainly that signing in is required.
            $message->line('You can download it from your payroll page after signing in.');
        }

        return $message->action('View your payslips', config('app.frontend_url').'/payroll');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payslip available',
            'message' => "Your payslip for {$this->slip->run?->title} is ready.",
            'salary_slip_id' => $this->slip->id,
            'slip_number' => $this->slip->slip_number,
        ];
    }
}
