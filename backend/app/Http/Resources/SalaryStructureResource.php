<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryStructureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,

            'country' => $this->country->value,
            'country_label' => $this->country->label(),
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->country->currencySymbol(),
            'is_active' => $this->is_active,

            'components' => SalaryComponentResource::collection($this->whenLoaded('components')),
            'components_count' => $this->whenCounted('components'),
            // Whether anyone is on this structure decides if deleting it is
            // safe, so the list can warn before the request is made.
            'assignments_count' => $this->whenCounted('assignments'),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
