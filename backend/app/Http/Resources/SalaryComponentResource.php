<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryComponentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salary_structure_id' => $this->salary_structure_id,
            'structure_name' => $this->whenLoaded('structure', fn () => $this->structure?->name),

            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'calculation' => $this->calculation->value,
            'value' => $this->value,

            'is_taxable' => $this->is_taxable,
            'is_statutory' => $this->is_statutory,
            'is_active' => $this->is_active,
            'country' => $this->country?->value,
            'sort_order' => $this->sort_order,

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
