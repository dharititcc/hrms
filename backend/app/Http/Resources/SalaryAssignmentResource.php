<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->staff_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->name),
            'salary_structure_id' => $this->salary_structure_id,
            'structure_name' => $this->whenLoaded('structure', fn () => $this->structure?->name),

            'basic_salary' => $this->basic_salary,
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->country->currencySymbol(),
            'country' => $this->country->value,

            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'status' => $this->status->value,
            'revision_reason' => $this->revision_reason,
            'supersedes_id' => $this->supersedes_id,

            'component_values' => $this->whenLoaded('componentValues', fn () => $this->componentValues->map(fn ($value) => [
                'salary_component_id' => $value->salary_component_id,
                'code' => $value->component?->code,
                'name' => $value->component?->name,
                'value' => $value->value,
            ])->all()),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
