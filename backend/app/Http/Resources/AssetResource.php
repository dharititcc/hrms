<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class AssetResource extends JsonResource{public function toArray(Request $r):array{return ['id'=>$this->id,'staff_id'=>$this->staff_id,'name'=>$this->name,'category'=>$this->category,'serial_number'=>$this->serial_number,'status'=>$this->status,'assigned_at'=>$this->assigned_at?->toDateString()];}}
