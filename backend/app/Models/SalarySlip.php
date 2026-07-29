<?php

namespace App\Models;

use App\Enums\PayrollCountry;
use App\Enums\SalaryComponentType;
use App\Enums\SalarySlipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id', 'payroll_run_id', 'staff_id', 'employee_salary_assignment_id',
    'slip_number', 'country', 'currency_code', 'basic_salary', 'total_earnings',
    'total_deductions', 'employer_contributions', 'gross_salary', 'net_salary',
    'paid_amount', 'status', 'emailed_at',
])]
#[Hidden(['owner_id'])]
class SalarySlip extends Model
{
    protected function casts(): array
    {
        return [
            'country' => PayrollCountry::class,
            'status' => SalarySlipStatus::class,
            'basic_salary' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'employer_contributions' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'emailed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** Stands in for the employer on a printed payslip. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryAssignment::class, 'employee_salary_assignment_id');
    }

    /** The frozen breakdown printed on the slip. */
    public function lines(): HasMany
    {
        return $this->hasMany(SalarySlipLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function earnings(): HasMany
    {
        return $this->lines()->where('type', SalaryComponentType::Earning);
    }

    public function deductions(): HasMany
    {
        return $this->lines()->where('type', SalaryComponentType::Deduction);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function outstanding(): float
    {
        return round((float) $this->net_salary - (float) $this->paid_amount, 2);
    }
}
