<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;use Illuminate\Database\Eloquent\Model;
#[Fillable(['owner_id','staff_id','title','description','progress','status','due_date'])] class PerformanceGoal extends Model{protected function casts():array{return ['due_date'=>'date'];}}
