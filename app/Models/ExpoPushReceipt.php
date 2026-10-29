<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpoPushReceipt extends Model
{
    protected $fillable = [
        'receipt_id',
        'expo_push_token',
        'attempts',
        'next_check_at',
        'state',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'next_check_at' => 'datetime',
        ];
    }
}
