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

    public function getDisplayBodyAttribute(): string
    {
        $text = trim((string) ($this->body_text ?? ''));
        if ($text === '') {
            return '';
        }

        if ($this->looksEncrypted($text)) {
            return '[Encrypted email content - cannot display without decryption key]';
        }

        if ($this->looksLikeRawMime($text)) {
            $text = $this->extractPlainTextFromRawMime($text);
        }

        $text = $this->normalizeText($text);
        $text = $this->stripQuotedReplyTrail($text);

        return trim($text) ?: '(No body)';
    }

    private function looksLikeRawMime(string $text): bool
    {
        return stripos($text, 'Content-Type: multipart/') !== false
            || stripos($text, 'Content-Type: text/plain') !== false;
    }

    private function looksEncrypted(string $text): bool
    {
        return stripos($text, '-----BEGIN PGP MESSAGE-----') !== false
            || stripos($text, 'content-type: application/pkcs7-mime') !== false
            || stripos($text, 'smime.p7m') !== false
            || stripos($text, 'content-type: application/pgp-encrypted') !== false;
    }

    private function extractPlainTextFromRawMime(string $raw): string
    {
        $normalized = str_replace("\r\n", "\n", $raw);
        $normalized = str_replace("\r", "\n", $normalized);

        if (preg_match('/Content-Type:\s*text\/plain\b.*?\n\n(.*?)(?:\n--[^\n]+|\z)/is', $normalized, $matches)) {
            $plain = quoted_printable_decode((string) $matches[1]);
            return $this->normalizeText($plain);
        }

        if (preg_match('/Content-Type:\s*text\/html\b.*?\n\n(.*?)(?:\n--[^\n]+|\z)/is', $normalized, $matches)) {
            $html = quoted_printable_decode((string) $matches[1]);
            $html = strip_tags($html);
            return $this->normalizeText($html);
        }

        return $this->normalizeText(quoted_printable_decode($normalized));
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace("/=\n/", '', $text) ?? $text; // quoted-printable soft break
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function stripQuotedReplyTrail(string $text): string
    {
        $patterns = [
            '/\nOn .+ wrote:\n.*/is',
            '/\nFrom:\s.+\nSent:\s.+\nTo:\s.+\nSubject:\s.+/is',
            '/\n-{2,}\s*Original Message\s*-{2,}\n.*/is',
            '/\nBegin forwarded message:\n.*/is',
        ];

        foreach ($patterns as $pattern) {
            $updated = preg_replace($pattern, '', $text);
            if (is_string($updated) && $updated !== $text) {
                $text = $updated;
                break;
            }
        }

        $lines = preg_split("/\n/", $text) ?: [];
        $cleanLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*>+/', $line)) {
                continue;
            }
            $cleanLines[] = $line;
        }

        return $this->normalizeText(implode("\n", $cleanLines));
    }
}
