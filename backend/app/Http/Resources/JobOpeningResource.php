<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class JobOpeningResource extends JsonResource{public function toArray(Request $r):array{return ['id'=>$this->id,'title'=>$this->title,'department'=>$this->department,'status'=>$this->status,'opened_at'=>$this->opened_at?->toDateString()];}}
