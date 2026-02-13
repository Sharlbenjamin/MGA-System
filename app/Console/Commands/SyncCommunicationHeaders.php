<?php

namespace App\Console\Commands;

use App\Services\GmailImapPollingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCommunicationHeaders extends Command
{
    protected $signature = 'communications:sync-headers
                            {--mailbox= : Mailbox to sync (defaults to MAIL_FROM_ADDRESS)}
                            {--limit=200 : Max new messages to process}';

    protected $description = 'Sync communication headers only for faster indexing';

    public function handle(GmailImapPollingService $service): int
    {
        $mailbox = (string) ($this->option('mailbox') ?: config('mail.from.address', 'mga.operation@medguarda.com'));
        $limit = (int) $this->option('limit');

        try {
            $result = $service->poll($mailbox, $limit, true);
            $this->info('Header sync complete.');
            $this->table(['Mailbox', 'Processed', 'New Threads', 'New Messages', 'Last UID'], [[
                $result['mailbox'] ?? $mailbox,
                $result['processed_uids'] ?? 0,
                $result['created_threads'] ?? 0,
                $result['created_messages'] ?? 0,
                $result['last_uid'] ?? '-',
            ]]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('communications:sync-headers failed', [
                'mailbox' => $mailbox,
                'error' => $e->getMessage(),
            ]);
            $this->error('Header sync failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
