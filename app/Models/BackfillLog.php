<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BackfillLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_class',
        'model_id',
        'field',
        'category',
        'google_link',
        'error_message',
        'attempts',
        'status',
        'last_attempt_at',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Mark log as successful
     */
    public function markAsSuccess(): void
    {
        $this->update([
            'status' => 'success',
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Mark log as retrying
     */
    public function markAsRetrying(): void
    {
        $this->update([
            'status' => 'retrying',
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Mark log as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Get logs by status
     */
    public static function getByStatus(string $status)
    {
        return static::where('status', $status)->get();
    }

    /**
     * Get failed logs older than specified hours
     */
    public static function getFailedOlderThan(int $hours)
    {
        return static::where('status', 'failed')
            ->where('last_attempt_at', '<', now()->subHours($hours))
            ->get();
    }

    /**
     * Get logs for a specific model
     */
    public static function getForModel(string $modelClass, int $modelId)
    {
        return static::where('model_class', $modelClass)
            ->where('model_id', $modelId)
            ->get();
    }
}
