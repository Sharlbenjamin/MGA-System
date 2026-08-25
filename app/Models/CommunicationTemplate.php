<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationContextType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationTemplate extends Model
{
    protected $fillable = [
        'name',
        'key',
        'context_type',
        'channel',
        'subject_template',
        'body_template',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'context_type' => CommunicationContextType::class,
        'channel' => CommunicationChannel::class,
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class, 'template_id');
    }
}
