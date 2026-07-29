<?php

namespace App\Models;

use App\Enums\PayrollCountry;
use App\Enums\PayrollRunStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id', 'title', 'country', 'currency_code', 'period_start', 'period_end',
    'pay_date', 'status', 'total_earnings', 'total_deductions', 'total_net',
    'slip_count', 'generated_by', 'approved_by', 'approved_at', 'notes',
])]
#[Hidden(['owner_id'])]
class PayrollRun extends Model
{
    use LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'country' => PayrollCountry::class,
            'status' => PayrollRunStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'pay_date' => 'date',
            'approved_at' => 'datetime',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
        ];
    }

    public function slips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
