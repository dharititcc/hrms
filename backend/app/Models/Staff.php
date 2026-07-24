<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['owner_id', 'name', 'email', 'phone', 'role', 'status'])]
#[Hidden(['owner_id'])]
class Staff extends Model
{
    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'status' => StaffStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
