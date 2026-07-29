<?php

namespace App\Models;

use App\Enums\PayrollCountry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['owner_id', 'name', 'description', 'country', 'currency_code', 'is_active'])]
#[Hidden(['owner_id'])]
class SalaryStructure extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'country' => PayrollCountry::class,
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryComponent::class)->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryAssignment::class);
    }
}
