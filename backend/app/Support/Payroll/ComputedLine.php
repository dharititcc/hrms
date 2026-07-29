<?php

namespace App\Support\Payroll;

use App\Enums\SalaryComponentType;

/**
 * One calculated line of a payslip. Immutable: once computed, a line is a
 * statement of fact about a pay period.
 */
final readonly class ComputedLine
{
    public function __construct(
        public SalaryComponentType $type,
        public string $code,
        public string $name,
        public float $amount,
        public bool $isStatutory = false,
        public int $sortOrder = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'code' => $this->code,
            'name' => $this->name,
            'amount' => $this->amount,
            'is_statutory' => $this->isStatutory,
            'sort_order' => $this->sortOrder,
        ];
    }
}
