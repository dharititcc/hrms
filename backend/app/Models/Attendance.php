<?php
namespace App\Models;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['owner_id','staff_id','work_date','check_in','check_out','status','notes'])]
class Attendance extends Model { protected function casts(): array { return ['work_date'=>'date','status'=>AttendanceStatus::class]; } public function owner(): BelongsTo { return $this->belongsTo(User::class,'owner_id'); } public function staff(): BelongsTo { return $this->belongsTo(Staff::class); } }
