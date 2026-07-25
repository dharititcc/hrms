<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['owner_id','payroll_period_id','staff_id','basic_salary','allowances','deductions','net_salary'])]
class PayrollRecord extends Model { protected function casts(): array { return ['basic_salary'=>'decimal:2','allowances'=>'decimal:2','deductions'=>'decimal:2','net_salary'=>'decimal:2']; } public function staff(): BelongsTo { return $this->belongsTo(Staff::class); } public function period(): BelongsTo { return $this->belongsTo(PayrollPeriod::class,'payroll_period_id'); } }
