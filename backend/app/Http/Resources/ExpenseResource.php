<?php
namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class ExpenseResource extends JsonResource { public function toArray(Request $r): array { return ['id'=>$this->id,'staff_id'=>$this->staff_id,'staff_name'=>$this->employee?->name,'title'=>$this->title,'category'=>$this->category,'amount'=>$this->amount,'expense_date'=>$this->expense_date?->toDateString(),'reason'=>$this->reason,'status'=>$this->status->value]; } }
