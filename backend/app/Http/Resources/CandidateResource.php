<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class CandidateResource extends JsonResource{public function toArray(Request $r):array{return ['id'=>$this->id,'job_opening_id'=>$this->job_opening_id,'name'=>$this->name,'email'=>$this->email,'stage'=>$this->stage,'notes'=>$this->notes];}}
