<?php

namespace App\Services;

use App\Models\CommunicationAttachment;
use App\Models\CommunicationMessage;
use App\Models\CommunicationSyncState;
use App\Models\CommunicationThread;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GmailImapPollingService
{
    /**
     * Poll Gmail IMAP inbox and mirror messages/attachments metadata into DB.
     *
     * @return array<string, int|string>
     */
    public function poll(string $mailbox = 'mga.operation@medguarda.com', int $limit = 100): array
    {
        if (!function_exists('imap_open')) {
            throw new \RuntimeException('PHP IMAP extension is not installed.');
        }

        $host = env('MAIL_IMAP_HOST', env('GMAIL_IMAP_HOST', 'imap.gmail.com'));
        $port = (int) env('MAIL_IMAP_PORT', env('GMAIL_IMAP_PORT', 993));
        $flags = env('MAIL_IMAP_FLAGS', env('GMAIL_IMAP_FLAGS', '/imap/ssl'));
        $username = env('MAIL_USERNAME', env('GMAIL_IMAP_USERNAME', $mailbox));
        $password = env('MAIL_PASSWORD', env('GMAIL_IMAP_PASSWORD'));

        if (!$password) {
            throw new \RuntimeException('Missing MAIL_PASSWORD env value.');
        }

        $mailboxPath = sprintf('{%s:%d%s}INBOX', $host, $port, $flags);
        $stream = @imap_open($mailboxPath, $username, $password);

        if (!$stream) {
            throw new \RuntimeException('IMAP connection failed: ' . (imap_last_error() ?: 'unknown'));
        }

        $sync = CommunicationSyncState::firstOrCreate(
            ['mailbox' => strtolower($mailbox)],
            ['last_uid' => 0]
        );

        $allUids = imap_search($stream, 'ALL', SE_UID) ?: [];
        sort($allUids);

        $uids = array_values(array_filter($allUids, fn ($uid) => (int) $uid > (int) $sync->last_uid));
        if ($limit > 0 && count($uids) > $limit) {
            $uids = array_slice($uids, -1 * $limit);
        }

        $createdThreads = 0;
        $createdMessages = 0;
        $maxUid = (int) $sync->last_uid;

        foreach ($uids as $uid) {
            $uid = (int) $uid;
            $maxUid = max($maxUid, $uid);
            $overviewRows = imap_fetch_overview($stream, (string) $uid, FT_UID);
            $overview = $overviewRows[0] ?? null;
            if (!$overview) {
                continue;
            }

            $messageId = $this->trimMessageId($overview->message_id ?? null);
            $inReplyToIds = $this->parseMessageIds((string) ($overview->in_reply_to ?? ''));
            $inReplyTo = $inReplyToIds[0] ?? null;
            $subject = $this->decodeMimeHeader($overview->subject ?? '');
            $normalizedSubject = $this->normalizeSubject($subject);
            $from = $this->parseSingleAddress($overview->from ?? '');
            $to = $this->parseAddressList($overview->to ?? '');
            $cc = $this->parseAddressList($overview->cc ?? '');
            $date = !empty($overview->date) ? Carbon::parse($overview->date) : now();
            $rawHeader = @imap_fetchheader($stream, (string) $uid, FT_UID | FT_PEEK) ?: '';
            $referenceIds = $this->extractReferenceMessageIds($rawHeader, $inReplyToIds);

            $existing = CommunicationMessage::query()
                ->where('mailbox', strtolower($mailbox))
                ->where('mailbox_uid', $uid)
                ->first();

            if ($existing) {
                continue;
            }

            $incomingParticipants = $this->mergeParticipants([], [
                $from['email'] ?? null,
                ...$to,
                ...$cc,
            ]);

            ['thread' => $thread, 'created' => $threadCreated] = $this->resolveThread(
                $mailbox,
                $subject,
                $normalizedSubject,
                $messageId,
                $inReplyTo,
                $referenceIds,
                $incomingParticipants
            );
            if ($threadCreated) {
                $createdThreads++;
            }

            $participants = $this->mergeParticipants($thread->participants ?? [], [
                $from['email'] ?? null,
                ...$to,
                ...$cc,
            ]);

            ['text' => $bodyText, 'html' => $bodyHtml] = $this->extractBodyContent($stream, $uid);
            $attachments = $this->extractAttachmentMetadata($stream, $uid);

            DB::transaction(function () use (
                $thread,
                $mailbox,
                $uid,
                $messageId,
                $inReplyTo,
                $from,
                $to,
                $cc,
                $subject,
                $bodyText,
                $bodyHtml,
                $date,
                $participants,
                $attachments
            ) {
                $message = CommunicationMessage::create([
                    'communication_thread_id' => $thread->id,
                    'mailbox' => strtolower($mailbox),
                    'mailbox_uid' => $uid,
                    'message_id' => $messageId,
                    'in_reply_to' => $inReplyTo,
                    'direction' => 'incoming',
                    'from_email' => strtolower($from['email'] ?? ''),
                    'from_name' => $from['name'] ?? null,
                    'to_emails' => array_map('strtolower', $to),
                    'cc_emails' => array_map('strtolower', $cc),
                    'subject' => $subject,
                    'body_text' => $bodyText,
                    'body_html' => $bodyHtml,
                    'sent_at' => $date,
                    'is_unread' => true,
                    'has_attachments' => !empty($attachments),
                    'metadata' => [
                        'source' => 'imap',
                    ],
                ]);

                foreach ($attachments as $attachment) {
                    CommunicationAttachment::create([
                        'communication_message_id' => $message->id,
                        'filename' => $attachment['filename'] ?? null,
                        'mime_type' => $attachment['mime_type'] ?? null,
                        'size_bytes' => $attachment['size_bytes'] ?? null,
                        'part_number' => $attachment['part_number'] ?? null,
                        'content_id' => $attachment['content_id'] ?? null,
                        'disposition' => $attachment['disposition'] ?? null,
                        'external_id' => null,
                        'url' => null,
                        'metadata' => ['mirrored_only' => true],
                    ]);
                }

                $thread->update([
                    'subject' => $thread->subject ?: $subject,
                    'participants' => $participants,
                    'category' => $this->inferCategory($participants),
                    'is_read' => false,
                    'awaiting_reply' => true,
                    'last_message_at' => $date,
                    'last_incoming_at' => $date,
                ]);
            });

            $createdMessages++;
        }

        imap_close($stream);

        $sync->update([
            'last_uid' => $maxUid,
            'last_polled_at' => now(),
            'last_error' => null,
        ]);

        return [
            'mailbox' => strtolower($mailbox),
            'processed_uids' => count($uids),
            'created_threads' => $createdThreads,
            'created_messages' => $createdMessages,
            'last_uid' => $maxUid,
        ];
    }

    private function resolveThread(
        string $mailbox,
        string $subject,
        string $normalizedSubject,
        ?string $messageId,
        ?string $inReplyTo,
        array $referenceIds = [],
        array $participants = []
    ): array {
        if ($inReplyTo) {
            $replyToMessage = CommunicationMessage::query()->where('message_id', $inReplyTo)->first();
            if ($replyToMessage) {
                return [
                    'thread' => $replyToMessage->thread,
                    'created' => false,
                ];
            }
        }

        if (!empty($referenceIds)) {
            $referenceMessage = CommunicationMessage::query()
                ->whereIn('message_id', $referenceIds)
                ->orderByDesc('sent_at')
                ->first();

            if ($referenceMessage) {
                return [
                    'thread' => $referenceMessage->thread,
                    'created' => false,
                ];
            }
        }

        if ($messageId) {
            $threadKeys = array_values(array_unique(array_filter([$messageId, ...$referenceIds])));
            $byMessageKey = CommunicationThread::query()
                ->where('mailbox', strtolower($mailbox))
                ->whereIn('external_thread_key', $threadKeys)
                ->first();
            if ($byMessageKey) {
                return [
                    'thread' => $byMessageKey,
                    'created' => false,
                ];
            }
        }

        $bySubject = CommunicationThread::query()
            ->where('mailbox', strtolower($mailbox))
            ->where('normalized_subject', $normalizedSubject)
            ->orderByDesc('last_message_at')
            ->first();

        if (!$bySubject && !empty($participants)) {
            $subjectCandidates = CommunicationThread::query()
                ->where('mailbox', strtolower($mailbox))
                ->where('normalized_subject', $normalizedSubject)
                ->orderByDesc('last_message_at')
                ->limit(20)
                ->get();

            $participantSet = array_flip(array_map('strtolower', $participants));
            foreach ($subjectCandidates as $candidate) {
                $candidateParticipants = array_map('strtolower', $candidate->participants ?? []);
                if (!empty(array_intersect_key($participantSet, array_flip($candidateParticipants)))) {
                    $bySubject = $candidate;
                    break;
                }
            }
        }

        if ($bySubject) {
            return [
                'thread' => $bySubject,
                'created' => false,
            ];
        }

        return [
            'thread' => CommunicationThread::create([
            'mailbox' => strtolower($mailbox),
            'subject' => $subject,
            'normalized_subject' => $normalizedSubject,
            'category' => 'unlinked',
            'is_read' => false,
            'awaiting_reply' => true,
            'external_thread_key' => $messageId,
            'participants' => [],
            ]),
            'created' => true,
        ];
    }

    /**
     * @return array{email: string|null, name: string|null}
     */
    private function parseSingleAddress(string $raw): array
    {
        $addresses = $this->parseAddressListWithNames($raw);
        return $addresses[0] ?? ['email' => null, 'name' => null];
    }

    /**
     * @return array<int, string>
     */
    private function parseAddressList(string $raw): array
    {
        return array_values(array_filter(array_map(
            fn ($item) => strtolower($item['email'] ?? ''),
            $this->parseAddressListWithNames($raw)
        )));
    }

    /**
     * @return array<int, array{email: string|null, name: string|null}>
     */
    private function parseAddressListWithNames(string $raw): array
    {
        $result = [];
        $items = imap_rfc822_parse_adrlist($raw, '') ?: [];

        foreach ($items as $item) {
            if (($item->host ?? '') === '.SYNTAX-ERROR.') {
                continue;
            }
            $email = isset($item->mailbox, $item->host) ? strtolower($item->mailbox . '@' . $item->host) : null;
            $name = isset($item->personal) ? $this->decodeMimeHeader($item->personal) : null;
            if ($email) {
                $result[] = ['email' => $email, 'name' => $name];
            }
        }

        return $result;
    }

    private function decodeMimeHeader(string $value): string
    {
        $parts = imap_mime_header_decode($value);
        $decoded = '';
        foreach ($parts as $part) {
            $decoded .= $part->text;
        }
        return trim($decoded ?: $value);
    }

    private function normalizeSubject(string $subject): string
    {
        $s = strtolower(trim($subject));
        do {
            $before = $s;
            $s = preg_replace('/^\s*(re|fw|fwd)\s*(\[[0-9]+\])?\s*:\s*/i', '', $s) ?? $s;
        } while ($s !== $before);
        return trim((string) $s);
    }

    private function trimMessageId(?string $messageId): ?string
    {
        if (!$messageId) {
            return null;
        }
        return trim(trim($messageId), '<>');
    }

    /**
     * @return array<int, string>
     */
    private function parseMessageIds(string $value): array
    {
        $ids = [];

        if (preg_match_all('/<([^>]+)>/', $value, $matches) && !empty($matches[1])) {
            $ids = $matches[1];
        } else {
            $tokens = preg_split('/[\s,;]+/', trim($value)) ?: [];
            foreach ($tokens as $token) {
                $token = $this->trimMessageId($token);
                if ($token) {
                    $ids[] = $token;
                }
            }
        }

        $ids = array_values(array_unique(array_filter(array_map(
            fn ($id) => strtolower((string) $this->trimMessageId((string) $id)),
            $ids
        ))));

        return $ids;
    }

    /**
     * @param array<int, string> $inReplyToIds
     * @return array<int, string>
     */
    private function extractReferenceMessageIds(string $rawHeader, array $inReplyToIds = []): array
    {
        $ids = $inReplyToIds;

        if (preg_match('/^References:\s*(.+?)(?:\r?\n[^\s]|\z)/ims', $rawHeader, $matches)) {
            $referencesRaw = preg_replace('/\r?\n[ \t]+/', ' ', (string) $matches[1]) ?? '';
            $ids = array_merge($ids, $this->parseMessageIds($referencesRaw));
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param array<int, string|null> $emails
     * @return array<int, string>
     */
    private function mergeParticipants(array $existing, array $emails): array
    {
        $all = array_map('strtolower', array_filter(array_merge($existing, $emails)));
        return array_values(array_unique($all));
    }

    /**
     * @param array<int, string> $participants
     */
    private function inferCategory(array $participants): string
    {
        if (empty($participants)) {
            return 'unlinked';
        }

        $providerFound = Provider::query()->whereIn('email', $participants)->exists();
        if ($providerFound) {
            return 'provider';
        }

        return 'general';
    }

    /**
     * Extract best text/plain body and html fallback from MIME structure.
     *
     * @return array{text: string, html: ?string}
     */
    private function extractBodyContent($stream, int $uid): array
    {
        $structure = @imap_fetchstructure($stream, (string) $uid, FT_UID);
        $rawBody = @imap_body($stream, (string) $uid, FT_UID | FT_PEEK) ?: '';

        if (!$structure) {
            return [
                'text' => $this->normalizeBodyText($rawBody),
                'html' => null,
            ];
        }

        $plainBodies = [];
        $htmlBodies = [];

        $walker = function ($part, string $partNumber = '1') use (&$walker, &$plainBodies, &$htmlBodies, $stream, $uid): void {
            if (!is_object($part)) {
                return;
            }

            if ($this->isAttachmentPart($part)) {
                return;
            }

            if (($part->type ?? null) === 1 && !empty($part->parts) && is_array($part->parts)) {
                foreach ($part->parts as $idx => $subPart) {
                    $walker($subPart, $partNumber . '.' . ($idx + 1));
                }
                return;
            }

            $body = @imap_fetchbody($stream, (string) $uid, $partNumber, FT_UID | FT_PEEK);
            if ($body === false || $body === '') {
                return;
            }

            $decoded = $this->decodePartBody($body, (int) ($part->encoding ?? 0));
            $decoded = $this->decodeToUtf8($decoded, $this->extractCharset($part));
            $decoded = $this->normalizeBodyText($decoded);

            $type = strtoupper($this->mimeFromPart($part) ?? '');
            if (str_starts_with($type, 'TEXT/PLAIN')) {
                $plainBodies[] = $decoded;
                return;
            }

            if (str_starts_with($type, 'TEXT/HTML')) {
                $htmlBodies[] = $decoded;
            }
        };

        if (($structure->type ?? null) === 1 && !empty($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $idx => $part) {
                $walker($part, (string) ($idx + 1));
            }
        } else {
            // Single-part message. `imap_body` is the part payload.
            $decoded = $this->decodePartBody($rawBody, (int) ($structure->encoding ?? 0));
            $decoded = $this->decodeToUtf8($decoded, $this->extractCharset($structure));
            $decoded = $this->normalizeBodyText($decoded);
            $type = strtoupper($this->mimeFromPart($structure) ?? '');

            if (str_starts_with($type, 'TEXT/HTML')) {
                $htmlBodies[] = $decoded;
            } else {
                $plainBodies[] = $decoded;
            }
        }

        $bodyHtml = $htmlBodies ? trim(implode("\n\n", $htmlBodies)) : null;
        $bodyText = trim(implode("\n\n", $plainBodies));

        if ($bodyText === '' && $bodyHtml) {
            $bodyText = $this->normalizeBodyText(strip_tags($bodyHtml));
        }

        if ($bodyText === '') {
            $bodyText = $this->normalizeBodyText($this->extractTextFromRawMime($rawBody));
        }

        $bodyText = $this->stripQuotedReplyTrail($bodyText);

        if ($bodyText === '') {
            $bodyText = '(No body)';
        }

        return [
            'text' => $bodyText,
            'html' => $bodyHtml,
        ];
    }

    private function isAttachmentPart(object $part): bool
    {
        $disposition = strtoupper((string) ($part->disposition ?? ''));
        if (in_array($disposition, ['ATTACHMENT'], true)) {
            return true;
        }

        if (!empty($part->ifdparameters) && !empty($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (in_array(strtolower((string) ($param->attribute ?? '')), ['filename'], true)) {
                    return true;
                }
            }
        }

        if (!empty($part->ifparameters) && !empty($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (in_array(strtolower((string) ($param->attribute ?? '')), ['name'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function decodePartBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body, true) ?: '',
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function extractCharset(object $part): ?string
    {
        if (!empty($part->ifparameters) && !empty($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower((string) ($param->attribute ?? '')) === 'charset') {
                    return (string) $param->value;
                }
            }
        }

        return null;
    }

    private function decodeToUtf8(string $body, ?string $charset): string
    {
        $charset = trim((string) $charset);
        if ($charset === '') {
            return $body;
        }

        if (strtoupper($charset) === 'UTF-8') {
            return $body;
        }

        $converted = @iconv($charset, 'UTF-8//IGNORE', $body);
        return $converted !== false ? $converted : $body;
    }

    private function normalizeBodyText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace("/=\n/", '', $text) ?? $text;
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

        return $this->normalizeBodyText(implode("\n", $cleanLines));
    }

    private function extractTextFromRawMime(string $raw): string
    {
        if (preg_match('/Content-Type:\s*text\/plain[^\n]*\n(?:[^\n]*\n)*?\n(.*?)(?:\n--[^\n]+|\z)/is', $raw, $matches)) {
            $text = quoted_printable_decode($matches[1]);
            return $this->normalizeBodyText($text);
        }

        return quoted_printable_decode($raw);
    }

    /**
     * Extract attachment metadata without storing files on disk.
     *
     * @return array<int, array<string, string|int|null>>
     */
    private function extractAttachmentMetadata($stream, int $uid): array
    {
        $structure = @imap_fetchstructure($stream, (string) $uid, FT_UID);
        if (!$structure) {
            return [];
        }

        $items = [];
        $walker = function ($part, string $partNumber = '1') use (&$walker, &$items): void {
            $isAttachment = false;
            $filename = null;
            $contentId = isset($part->id) ? trim($part->id, '<>') : null;
            $disposition = $part->disposition ?? null;

            if (!empty($part->ifdparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute ?? '') === 'filename') {
                        $isAttachment = true;
                        $filename = $param->value;
                    }
                }
            }

            if (!$filename && !empty($part->ifparameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute ?? '') === 'name') {
                        $isAttachment = true;
                        $filename = $param->value;
                    }
                }
            }

            if ($isAttachment || in_array(strtoupper((string) $disposition), ['ATTACHMENT', 'INLINE'], true)) {
                $items[] = [
                    'filename' => $filename,
                    'mime_type' => $this->mimeFromPart($part),
                    'size_bytes' => $part->bytes ?? null,
                    'part_number' => $partNumber,
                    'content_id' => $contentId,
                    'disposition' => $disposition,
                ];
            }

            if (!empty($part->parts) && is_array($part->parts)) {
                foreach ($part->parts as $idx => $subPart) {
                    $walker($subPart, $partNumber . '.' . ($idx + 1));
                }
            }
        };

        if (!empty($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $idx => $part) {
                $walker($part, (string) ($idx + 1));
            }
        } else {
            $walker($structure, '1');
        }

        return $items;
    }

    private function mimeFromPart(object $part): ?string
    {
        $types = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER'];
        $type = $types[$part->type ?? 7] ?? 'OTHER';
        $subtype = strtoupper($part->subtype ?? 'OCTET-STREAM');
        return $type . '/' . $subtype;
    }
}
