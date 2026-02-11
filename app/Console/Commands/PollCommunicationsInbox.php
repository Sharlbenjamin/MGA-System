<?php

namespace App\Console\Commands;

use App\Services\GmailImapPollingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollCommunicationsInbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'communications:poll
                            {--mailbox=mga.operation@medguarda.com : Mailbox to poll}
                            {--limit=100 : Max new messages to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Gmail inbox via IMAP and mirror communications data';

    /**
     * Execute the console command.
     */
    public function handle(GmailImapPollingService $service): int
    {
        $mailbox = (string) $this->option('mailbox');
        $limit = (int) $this->option('limit');

        try {
            $result = $service->poll($mailbox, $limit);
            $this->info('Polling complete.');
            $this->table(['Mailbox', 'Processed', 'New Threads', 'New Messages', 'Last UID'], [[
                $result['mailbox'] ?? $mailbox,
                $result['processed_uids'] ?? 0,
                $result['created_threads'] ?? 0,
                $result['created_messages'] ?? 0,
                $result['last_uid'] ?? '-',
            ]]);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('communications:poll failed', [
                'mailbox' => $mailbox,
                'error' => $e->getMessage(),
            ]);
            $this->error('Polling failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
