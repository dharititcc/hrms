<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalarySlipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slip_number' => $this->slip_number,
            'payroll_run_id' => $this->payroll_run_id,
            'period' => $this->whenLoaded('run', fn () => $this->run?->title),
            'period_start' => $this->whenLoaded('run', fn () => $this->run?->period_start?->toDateString()),
            'period_end' => $this->whenLoaded('run', fn () => $this->run?->period_end?->toDateString()),

            'employee_id' => $this->staff_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->name),

            'country' => $this->country->value,
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->country->currencySymbol(),

            'basic_salary' => $this->basic_salary,
            'total_earnings' => $this->total_earnings,
            'total_deductions' => $this->total_deductions,
            'employer_contributions' => $this->employer_contributions,
            'gross_salary' => $this->gross_salary,
            'net_salary' => $this->net_salary,
            'paid_amount' => $this->paid_amount,
            'outstanding' => $this->outstanding(),

            'status' => $this->status->value,
            'payments' => SalaryPaymentResource::collection($this->whenLoaded('payments')),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'type' => $line->type->value,
                'code' => $line->code,
                'name' => $line->name,
                'amount' => $line->amount,
                'is_statutory' => $line->is_statutory,
            ])->all()),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
