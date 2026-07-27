<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;use Illuminate\Database\Eloquent\Model;
#[Fillable(['owner_id','user_id','action','entity','entity_id','metadata'])] class AuditLog extends Model{protected function casts():array{return ['metadata'=>'array'];}}
