<?php

namespace App\Traits;

use App\Services\ActivityLogger;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            ActivityLogger::log(\App\Models\ActivityLog::ACTION_CREATED, $model);
        });

        static::updated(function ($model) {
            $changes = ActivityLogger::buildChangesFromModel($model);
            if (! empty($changes)) {
                ActivityLogger::log(\App\Models\ActivityLog::ACTION_UPDATED, $model, $changes);
            }
        });

        static::deleting(function ($model) {
            ActivityLogger::log(\App\Models\ActivityLog::ACTION_DELETED, $model);
        });
    }
}
