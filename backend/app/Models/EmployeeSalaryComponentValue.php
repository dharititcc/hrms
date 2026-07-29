<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_salary_assignment_id', 'salary_component_id', 'value'])]
class EmployeeSalaryComponentValue extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:4'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryAssignment::class, 'employee_salary_assignment_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
