<?php
namespace App\Models;
use App\Enums\PayrollStatus; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['owner_id','name','start_date','end_date','status'])]
class PayrollPeriod extends Model { protected function casts(): array { return ['start_date'=>'date','end_date'=>'date','status'=>PayrollStatus::class]; } public function owner(): BelongsTo { return $this->belongsTo(User::class,'owner_id'); } }
