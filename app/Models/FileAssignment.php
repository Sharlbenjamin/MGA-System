<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAssignment extends Model
{
    protected $fillable = [
        'file_id',
        'user_id',
        'assigned_by_id',
        'assigned_at',
        'unassigned_at',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('unassigned_at');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
