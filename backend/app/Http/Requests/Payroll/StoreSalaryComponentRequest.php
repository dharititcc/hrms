<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollCountry;
use App\Enums\SalaryCalculation;
use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $ownerId = $this->user()->workspaceOwnerId();

        return [
            // Codes are how manual amounts are keyed at generation time, so
            // they are uppercased and kept free of punctuation.
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SalaryComponentType::class)],
            'calculation' => ['required', Rule::enum(SalaryCalculation::class)],

            // A percentage over 100 is almost always a rate entered as a
            // fraction the wrong way round; the ceiling is checked below.
            'value' => ['required', 'numeric', 'min:0', 'max:99999999.9999'],

            'salary_structure_id' => [
                'nullable', 'integer',
                Rule::exists('salary_structures', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at'),
            ],

            'is_taxable' => ['nullable', 'boolean'],
            'is_statutory' => ['nullable', 'boolean'],
            'country' => ['nullable', Rule::enum(PayrollCountry::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectSelfReferentialEarning($validator);
            $this->rejectImpossiblePercentage($validator);
            $this->rejectDuplicateCode($validator);
        });
    }

    public function code(): string
    {
        return strtoupper($this->input('code'));
    }

    /**
     * The calculator throws on a percentage-of-gross earning, because gross
     * includes earnings so the value would depend on itself. Catching it here
     * means the component cannot be saved at all, rather than blowing up
     * later when someone generates a run.
     */
    private function rejectSelfReferentialEarning(Validator $validator): void
    {
        $isEarning = $this->input('type') === SalaryComponentType::Earning->value;
        $ofGross = $this->input('calculation') === SalaryCalculation::PercentOfGross->value;

        if ($isEarning && $ofGross) {
            $validator->errors()->add(
                'calculation',
                'An earning cannot be a percentage of gross, because gross includes earnings. Use a percentage of basic, or a fixed amount.',
            );
        }
    }

    private function rejectImpossiblePercentage(Validator $validator): void
    {
        $calculation = SalaryCalculation::tryFrom((string) $this->input('calculation'));

        if ($calculation?->isDerived() && (float) $this->input('value') > 100) {
            $validator->errors()->add('value', 'A percentage cannot exceed 100.');
        }
    }

    /**
     * The database's unique index spans (owner_id, salary_structure_id, code),
     * but MySQL treats NULLs as distinct, so it does not stop two
     * workspace-wide components sharing a code. Both would then be applied to
     * every payslip. Checked here instead.
     */
    private function rejectDuplicateCode(Validator $validator): void
    {
        $structureId = $this->input('salary_structure_id');

        $editing = $this->route('component');

        $exists = SalaryComponent::query()
            ->where('owner_id', $this->user()->workspaceOwnerId())
            ->where('code', $this->code())
            ->when($structureId === null,
                fn ($query) => $query->whereNull('salary_structure_id'),
                fn ($query) => $query->where('salary_structure_id', $structureId),
            )
            // "id != null" is never true in SQL, so the exclusion has to be
            // conditional or the check would silently pass on every create.
            ->when($editing !== null, fn ($query) => $query->whereKeyNot($editing->id))
            ->exists();

        if ($exists) {
            $validator->errors()->add('code', 'A component with this code already exists here.');
        }
    }
}
