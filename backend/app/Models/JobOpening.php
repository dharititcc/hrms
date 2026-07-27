<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable(['owner_id','title','department','status','opened_at'])] class JobOpening extends Model{protected function casts():array{return ['opened_at'=>'date'];}}
