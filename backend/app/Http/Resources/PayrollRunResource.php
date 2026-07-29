<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'country' => $this->country->value,
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->country->currencySymbol(),

            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'pay_date' => $this->pay_date?->toDateString(),

            'status' => $this->status->value,
            'is_editable' => $this->status->isEditable(),
            'is_locked' => $this->status->isLocked(),

            'slip_count' => $this->slip_count,
            'total_earnings' => $this->total_earnings,
            'total_deductions' => $this->total_deductions,
            'total_net' => $this->total_net,

            'generated_by' => $this->generated_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toISOString(),
            'notes' => $this->notes,

            'slips' => SalarySlipResource::collection($this->whenLoaded('slips')),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
