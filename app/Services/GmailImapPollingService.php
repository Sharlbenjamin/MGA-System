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
            $inReplyTo = $this->trimMessageId($overview->in_reply_to ?? null);
            $subject = $this->decodeMimeHeader($overview->subject ?? '');
            $normalizedSubject = $this->normalizeSubject($subject);
            $from = $this->parseSingleAddress($overview->from ?? '');
            $to = $this->parseAddressList($overview->to ?? '');
            $cc = $this->parseAddressList($overview->cc ?? '');
            $date = !empty($overview->date) ? Carbon::parse($overview->date) : now();

            $existing = CommunicationMessage::query()
                ->where('mailbox', strtolower($mailbox))
                ->where('mailbox_uid', $uid)
                ->first();

            if ($existing) {
                continue;
            }

            $thread = $this->resolveThread($mailbox, $subject, $normalizedSubject, $messageId, $inReplyTo);
            if (!$thread->exists) {
                $createdThreads++;
            }

            $participants = $this->mergeParticipants($thread->participants ?? [], [
                $from['email'] ?? null,
                ...$to,
                ...$cc,
            ]);

            $rawBody = @imap_body($stream, (string) $uid, FT_UID | FT_PEEK) ?: '';
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
                $rawBody,
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
                    'body_text' => $rawBody,
                    'body_html' => null,
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
        ?string $inReplyTo
    ): CommunicationThread {
        if ($inReplyTo) {
            $replyToMessage = CommunicationMessage::query()->where('message_id', $inReplyTo)->first();
            if ($replyToMessage) {
                return $replyToMessage->thread;
            }
        }

        if ($messageId) {
            $byMessageKey = CommunicationThread::query()
                ->where('mailbox', strtolower($mailbox))
                ->where('external_thread_key', $messageId)
                ->first();
            if ($byMessageKey) {
                return $byMessageKey;
            }
        }

        $bySubject = CommunicationThread::query()
            ->where('mailbox', strtolower($mailbox))
            ->where('normalized_subject', $normalizedSubject)
            ->orderByDesc('last_message_at')
            ->first();

        if ($bySubject) {
            return $bySubject;
        }

        return CommunicationThread::create([
            'mailbox' => strtolower($mailbox),
            'subject' => $subject,
            'normalized_subject' => $normalizedSubject,
            'category' => 'unlinked',
            'is_read' => false,
            'awaiting_reply' => true,
            'external_thread_key' => $messageId,
            'participants' => [],
        ]);
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
        $s = preg_replace('/^(re|fw|fwd)\s*:\s*/i', '', $s);
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
