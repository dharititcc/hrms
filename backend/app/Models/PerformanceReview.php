<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;use Illuminate\Database\Eloquent\Model;
#[Fillable(['owner_id','staff_id','reviewer_id','period','score','feedback'])] class PerformanceReview extends Model{protected function casts():array{return ['score'=>'decimal:2'];}}
