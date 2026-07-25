<?php
namespace App\Models;
use App\Enums\ExpenseStatus; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['owner_id','staff_id','title','category','amount','expense_date','reason','status'])]
class Expense extends Model { protected function casts(): array { return ['amount'=>'decimal:2','expense_date'=>'date','status'=>ExpenseStatus::class]; } public function staff(): BelongsTo { return $this->belongsTo(Staff::class); } }
