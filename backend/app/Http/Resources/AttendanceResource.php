<?php
namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class AttendanceResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'staff_id'=>$this->staff_id,'staff_name'=>$this->staff?->name,'work_date'=>$this->work_date?->toDateString(),'check_in'=>$this->check_in,'check_out'=>$this->check_out,'status'=>$this->status->value,'notes'=>$this->notes]; } }
