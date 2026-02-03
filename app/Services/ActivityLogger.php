<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Log an activity for a model.
     *
     * @param  string  $action  One of ActivityLog::ACTION_CREATED, ACTION_UPDATED, ACTION_DELETED
     * @param  Model  $model  The model that was created/updated/deleted
     * @param  array|null  $changes  For updates: ['attribute' => ['old' => x, 'new' => y], ...]
     */
    public static function log(string $action, Model $model, ?array $changes = null): void
    {
        $reference = static::getSubjectReference($model);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'subject_reference' => $reference,
            'changes' => $changes,
        ]);
    }

    /**
     * Get a human-readable reference for the model (e.g. case ref, provider name).
     */
    public static function getSubjectReference(Model $model): ?string
    {
        if (method_exists($model, 'getActivityReference')) {
            return $model->getActivityReference();
        }

        $short = class_basename($model);
        $id = $model->getKey();

        return "{$short} #{$id}";
    }

    /**
     * Build changes array from model's getChanges() and getOriginal().
     */
    public static function buildChangesFromModel(Model $model): array
    {
        $changes = [];
        $dirty = $model->getChanges();

        foreach ($dirty as $key => $newValue) {
            if (in_array($key, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $oldValue = $model->getOriginal($key);
            $changes[$key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }
}
