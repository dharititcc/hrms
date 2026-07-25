<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['owner_id','name','days_per_year','is_active'])]
class LeaveType extends Model { protected function casts(): array { return ['is_active'=>'boolean','days_per_year'=>'integer']; } public function owner(): BelongsTo { return $this->belongsTo(User::class,'owner_id'); } }
