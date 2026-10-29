<?php

namespace App\Console\Commands;

use App\Services\ExpoPushReceiptService;
use Illuminate\Console\Command;

class ProcessExpoPushReceiptsCommand extends Command
{
    protected $signature = 'push:process-expo-receipts {--limit=100 : Max receipts per run}';

    protected $description = 'Fetch pending Expo push delivery receipts (retries with backoff)';

    public function handle(ExpoPushReceiptService $service): int
    {
        $count = $service->processDue((int) $this->option('limit'));
        $this->info("Processed {$count} Expo push receipt(s).");

        return self::SUCCESS;
    }
}
