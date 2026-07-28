<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;

/**
 * Records create/update/delete events against the activity timeline.
 *
 * Add to any workspace-scoped model. Updates that only touch timestamps are
 * skipped so the timeline stays meaningful.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model): void {
            app(ActivityLogger::class)->log('created', $model);
        });

        static::updated(function ($model): void {
            $logger = app(ActivityLogger::class);
            $changes = $logger->changeSet($model);

            if ($changes !== null) {
                $logger->log('updated', $model, $changes);
            }
        });

        static::deleted(function ($model): void {
            app(ActivityLogger::class)->log('deleted', $model);
        });
    }
}
