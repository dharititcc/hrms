<?php
namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class PayrollRecordResource extends JsonResource { public function toArray(Request $r): array { return ['id'=>$this->id,'period'=>$this->period?->name,'staff_id'=>$this->staff_id,'staff_name'=>$this->staff?->name,'basic_salary'=>$this->basic_salary,'allowances'=>$this->allowances,'deductions'=>$this->deductions,'net_salary'=>$this->net_salary]; } }
