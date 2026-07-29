<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['owner_id', 'name', 'starts_at', 'ends_at', 'grace_minutes', 'break_minutes', 'is_default', 'is_active'])]
#[Hidden(['owner_id'])]
class WorkShift extends Model
{
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'break_minutes' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The workspace's default shift, or null to fall back to config. */
    public static function defaultFor(int $ownerId): ?self
    {
        return static::query()->where('owner_id', $ownerId)->active()->where('is_default', true)->first();
    }
}
