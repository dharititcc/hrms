<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;use Illuminate\Database\Eloquent\Model;
#[Fillable(['owner_id','title','body','published_at','status'])] class Announcement extends Model{protected function casts():array{return ['published_at'=>'datetime'];}}
