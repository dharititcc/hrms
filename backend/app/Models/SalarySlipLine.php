<?php

namespace App\Models;

use App\Enums\SalaryComponentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['salary_slip_id', 'type', 'code', 'name', 'amount', 'is_statutory', 'sort_order'])]
class SalarySlipLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class,
            'amount' => 'decimal:2',
            'is_statutory' => 'boolean',
        ];
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slip_id');
    }
}
