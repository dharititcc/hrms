<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;use Illuminate\Database\Eloquent\Model;
#[Fillable(['owner_id','staff_id','name','category','serial_number','status','assigned_at'])] class Asset extends Model{protected function casts():array{return ['assigned_at'=>'date'];}}
