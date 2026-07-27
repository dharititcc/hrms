<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class AnnouncementResource extends JsonResource{public function toArray(Request $r):array{return ['id'=>$this->id,'title'=>$this->title,'body'=>$this->body,'status'=>$this->status,'published_at'=>$this->published_at?->toISOString()];}}
