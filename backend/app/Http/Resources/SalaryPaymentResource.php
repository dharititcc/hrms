<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salary_slip_id' => $this->salary_slip_id,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'paid_at' => $this->paid_at?->toISOString(),
            'method' => $this->method,
            'reference' => $this->reference,
            'note' => $this->note,
            'recorded_by' => $this->recorded_by,
            'recorded_by_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
