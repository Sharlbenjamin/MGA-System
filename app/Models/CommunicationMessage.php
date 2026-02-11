<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CommunicationAttachment;

class CommunicationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_thread_id',
        'mailbox',
        'mailbox_uid',
        'message_id',
        'in_reply_to',
        'direction',
        'from_email',
        'from_name',
        'to_emails',
        'cc_emails',
        'bcc_emails',
        'subject',
        'body_text',
        'body_html',
        'sent_at',
        'is_unread',
        'has_attachments',
        'metadata',
    ];

    protected $casts = [
        'mailbox_uid' => 'integer',
        'to_emails' => 'array',
        'cc_emails' => 'array',
        'bcc_emails' => 'array',
        'sent_at' => 'datetime',
        'is_unread' => 'boolean',
        'has_attachments' => 'boolean',
        'metadata' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'communication_thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CommunicationAttachment::class);
    }
}
