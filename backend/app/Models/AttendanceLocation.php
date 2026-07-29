<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['owner_id', 'name', 'address', 'latitude', 'longitude', 'radius_metres', 'is_active'])]
#[Hidden(['owner_id'])]
class AttendanceLocation extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_metres' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Great-circle distance in metres between this location and a point.
     *
     * Haversine rather than a flat approximation: a plane projection drifts
     * badly at high latitudes, and a geofence that quietly widens near the
     * poles is worse than none.
     */
    public function distanceTo(float $latitude, float $longitude): float
    {
        $earthRadius = 6_371_000;

        $latDelta = deg2rad($latitude - $this->latitude);
        $lngDelta = deg2rad($longitude - $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function covers(float $latitude, float $longitude): bool
    {
        return $this->distanceTo($latitude, $longitude) <= $this->radius_metres;
    }
}
