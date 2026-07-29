<?php
namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class LeaveRequestResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'staff_id'=>$this->staff_id,'staff_name'=>$this->employee?->name,'leave_type_id'=>$this->leave_type_id,'leave_type'=>$this->leaveType?->name,'start_date'=>$this->start_date?->toDateString(),'end_date'=>$this->end_date?->toDateString(),'reason'=>$this->reason,'status'=>$this->status->value]; } }
