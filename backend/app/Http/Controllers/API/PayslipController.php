<?php

namespace App\Http\Controllers\API;

use App\Enums\Module;
use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Services\Payroll\PayslipDeliveryService;
use App\Services\Payroll\PayslipPdfService;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payslip documents: downloading one, and sending them out.
 */
class PayslipController extends Controller
{
    public function __construct(
        private readonly PayslipPdfService $pdf,
        private readonly PayslipDeliveryService $delivery,
    ) {}

    /**
     * The PDF itself.
     *
     * An employee has payroll.download but not payroll.view-all, so the record
     * scope is what stops them fetching a colleague's by guessing an id.
     */
    public function download(Request $request, SalarySlip $slip): Response
    {
        $this->authorizeSlip($request, $slip);

        /*
        | Refused before approval. A draft is working figures, and a PDF that
        | says "payslip" is treated as final by whoever receives it.
        */
        abort_unless(
            in_array($slip->run?->status, [PayrollRunStatus::Approved, PayrollRunStatus::Paid], strict: true),
            422,
            'This payroll has not been approved yet, so its payslips cannot be downloaded.',
        );

        return response($this->pdf->render($slip), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->pdf->filename($slip).'"',
        ]);
    }

    /** Emails every payslip in an approved run. */
    public function emailRun(Request $request, PayrollRun $run): JsonResponse
    {
        abort_unless($run->owner_id === $request->user()->workspaceOwnerId(), 403);

        // Resending is deliberate rather than accidental: without this, a
        // second click quietly does nothing and looks like a failure.
        $result = $this->delivery->sendRun($run, $request->boolean('resend'));

        return response()->json([
            'message' => $this->summarise($result),
            'meta' => $result,
        ]);
    }

    /** Emails one payslip, for the person whose bounced. */
    public function email(Request $request, SalarySlip $slip): JsonResponse
    {
        abort_unless($slip->owner_id === $request->user()->workspaceOwnerId(), 403);

        $sent = $this->delivery->send($slip);

        return response()->json([
            'message' => $sent
                ? 'Payslip sent.'
                : 'This employee has no account yet, so there is nowhere to send it. Invite them first.',
            'meta' => ['sent' => $sent],
        ], $sent ? 200 : 422);
    }

    /** @param array{sent: int, skipped_without_account: int, skipped_already_sent: int} $result */
    private function summarise(array $result): string
    {
        $parts = ["{$result['sent']} payslip(s) sent"];

        if ($result['skipped_already_sent'] > 0) {
            $parts[] = "{$result['skipped_already_sent']} already sent";
        }

        if ($result['skipped_without_account'] > 0) {
            $parts[] = "{$result['skipped_without_account']} skipped with no account";
        }

        return implode(', ', $parts).'.';
    }

    private function authorizeSlip(Request $request, SalarySlip $slip): void
    {
        abort_unless($slip->owner_id === $request->user()->workspaceOwnerId(), 403);
        abort_unless(RecordScope::allows($request->user(), Module::Payroll, $slip->staff_id), 403);
    }
}
