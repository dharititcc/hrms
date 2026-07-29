<?php

namespace App\Models;

use App\Enums\PayrollCountry;
use App\Enums\SalaryAssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What an employee is paid, from a date. A revision is a new row; the previous
 * one is marked superseded, so salary history stays intact.
 */
#[Fillable([
    'owner_id', 'staff_id', 'salary_structure_id', 'basic_salary', 'currency_code',
    'country', 'effective_from', 'effective_to', 'status', 'revision_reason',
    'supersedes_id', 'created_by',
])]
#[Hidden(['owner_id'])]
class EmployeeSalaryAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'country' => PayrollCountry::class,
            'status' => SalaryAssignmentStatus::class,
            'basic_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    /** Per-employee amounts overriding the structure's defaults. */
    public function componentValues(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponentValue::class);
    }

    /** The revision this one replaced. */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SalaryAssignmentStatus::Active);
    }

    /** The assignment in force on a given date. */
    public function scopeEffectiveOn(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }
}
