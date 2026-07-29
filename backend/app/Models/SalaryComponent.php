<?php

namespace App\Models;

use App\Enums\PayrollCountry;
use App\Enums\SalaryCalculation;
use App\Enums\SalaryComponentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'owner_id', 'salary_structure_id', 'code', 'name', 'type', 'calculation',
    'value', 'is_taxable', 'is_statutory', 'country', 'sort_order', 'is_active',
])]
#[Hidden(['owner_id'])]
class SalaryComponent extends Model
{
    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class,
            'calculation' => SalaryCalculation::class,
            'country' => PayrollCountry::class,
            'value' => 'decimal:4',
            'is_taxable' => 'boolean',
            'is_statutory' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
