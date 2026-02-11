<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CommunicationMessage;

class CommunicationThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox',
        'subject',
        'normalized_subject',
        'category',
        'linked_file_id',
        'is_read',
        'awaiting_reply',
        'last_message_at',
        'last_incoming_at',
        'external_thread_key',
        'participants',
        'metadata',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'awaiting_reply' => 'boolean',
        'last_message_at' => 'datetime',
        'last_incoming_at' => 'datetime',
        'participants' => 'array',
        'metadata' => 'array',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'linked_file_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class);
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class)->latest('sent_at')->limit(1);
    }
}
