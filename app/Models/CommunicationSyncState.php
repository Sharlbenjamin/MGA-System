<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationSyncState extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox',
        'last_uid',
        'last_polled_at',
        'last_error',
    ];

    protected $casts = [
        'last_uid' => 'integer',
        'last_polled_at' => 'datetime',
    ];
}
