<?php

namespace App\Services\Payroll;

use App\Enums\SalaryComponentType;
use App\Models\SalarySlip;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a payslip to PDF.
 *
 * Everything is read from the slip's frozen columns and lines rather than
 * recalculated, so reprinting one a year later produces the same document as
 * the day it was issued — even if the structure it came from has since been
 * corrected.
 */
class PayslipPdfService
{
    /** @return string raw PDF bytes */
    public function render(SalarySlip $slip): string
    {
        $slip->loadMissing(['staff', 'run', 'lines', 'owner']);

        return Pdf::loadView('payroll.payslip', $this->data($slip))
            ->setPaper('a4')
            ->output();
    }

    /**
     * A filename someone can find again in a downloads folder, which means the
     * period and the person, not just an id.
     */
    public function filename(SalarySlip $slip): string
    {
        $parts = array_filter([
            $slip->slip_number,
            $slip->staff?->name,
            $slip->run?->period_end?->format('Y-m'),
        ]);

        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', implode(' ', $parts)) ?? 'payslip';

        return trim($slug, '-').'.pdf';
    }

    /** @return array<string, mixed> */
    private function data(SalarySlip $slip): array
    {
        $lines = $slip->lines;
        $symbol = $slip->country->currencySymbol();

        return [
            'slip' => $slip,
            // The workspace owner's name stands in for the employer: there is
            // no separate company record to read one from.
            'employer' => $slip->owner?->name ?? 'Employer',
            'period' => $slip->run?->title ?? 'Payslip',
            'payDate' => $slip->run?->pay_date?->format('j M Y')
                ?? $slip->run?->period_end?->format('j M Y')
                ?? '—',
            'approvedAt' => $slip->run?->approved_at?->format('j M Y'),

            'earnings' => $lines->where('type', SalaryComponentType::Earning)->values(),
            'deductions' => $lines->where('type', SalaryComponentType::Deduction)->values(),
            'contributions' => $lines->where('type', SalaryComponentType::EmployerContribution)->values(),

            // Passed in rather than formatted in the template, so currency
            // handling lives in one place.
            'money' => fn ($amount) => $symbol.number_format((float) $amount, 2),
        ];
    }
}
