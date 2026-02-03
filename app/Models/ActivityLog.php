<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_reference',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human-readable model name for display (e.g. "Case", "Provider", "Client").
     */
    public function getSubjectTypeLabelAttribute(): string
    {
        $type = $this->subject_type;
        if (!$type) {
            return 'Unknown';
        }
        $short = class_basename($type);
        return match ($short) {
            'File' => 'Case',
            'Provider' => 'Provider',
            'Client' => 'Client',
            'Patient' => 'Patient',
            default => $short,
        };
    }

    /**
     * Human-readable action description.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            default => ucfirst($this->action),
        };
    }
}
