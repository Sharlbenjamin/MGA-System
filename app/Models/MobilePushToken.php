<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilePushToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'notification_sound',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
