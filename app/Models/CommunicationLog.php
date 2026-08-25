<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationContextType;
use App\Enums\CommunicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationLog extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'recipient_type',
        'recipient_id',
        'provider_id',
        'provider_branch_id',
        'file_id',
        'context_type',
        'context_id',
        'template_id',
        'subject',
        'body',
        'status',
        'external_message_id',
        'external_thread_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'channel' => CommunicationChannel::class,
        'context_type' => CommunicationContextType::class,
        'status' => CommunicationStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function providerBranch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
