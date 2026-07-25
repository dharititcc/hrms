<?php
namespace App\Models;
use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['owner_id','staff_id','leave_type_id','start_date','end_date','reason','status','reviewed_by'])]
class LeaveRequest extends Model { protected function casts(): array { return ['start_date'=>'date','end_date'=>'date','status'=>LeaveRequestStatus::class]; } public function owner(): BelongsTo { return $this->belongsTo(User::class,'owner_id'); } public function staff(): BelongsTo { return $this->belongsTo(Staff::class); } public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); } }
