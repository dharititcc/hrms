<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class GoalResource extends JsonResource{public function toArray(Request $r):array{return ['id'=>$this->id,'staff_id'=>$this->staff_id,'title'=>$this->title,'description'=>$this->description,'progress'=>$this->progress,'status'=>$this->status,'due_date'=>$this->due_date?->toDateString()];}}
