<?php

namespace App\Console\Commands;

use App\Services\BulkTransactionPdfService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateTransactionPdfs extends Command
{
    protected $signature = 'transactions:generate-pdfs
                            {--year= : Year to process}
                            {--quarter=1 : Quarter (1-4 or full)}
                            {--scope=both : receivables, bulk_bills, or both}
                            {--regenerate : Regenerate PDFs even if they already exist}';

    protected $description = 'Bulk generate Trx In/Out PDFs for receivables and bulk bill transactions';

    public function handle(BulkTransactionPdfService $service): int
    {
        $year = (int) ($this->option('year') ?: Carbon::now()->year);
        $quarter = (string) $this->option('quarter');
        $scope = (string) $this->option('scope');
        $regenerate = (bool) $this->option('regenerate');

        if (! in_array($scope, ['receivables', 'bulk_bills', 'both'], true)) {
            $this->error('Invalid scope. Use receivables, bulk_bills, or both.');

            return self::FAILURE;
        }

        $this->info("Generating PDFs for {$year} Q{$quarter} (scope: {$scope})...");

        $result = $service->generateForPeriod($year, $quarter, $scope, $regenerate);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Generated', $result->generated],
                ['Skipped', $result->skipped],
                ['Failed', $result->failed],
            ]
        );

        if ($result->skippedDetails !== []) {
            $this->newLine();
            $this->warn('Skipped transactions:');
            foreach (array_slice($result->skippedDetails, 0, 20) as $detail) {
                $this->line("  #{$detail['transaction_id']}: {$detail['reason']}");
            }
            if (count($result->skippedDetails) > 20) {
                $this->line('  ... and ' . (count($result->skippedDetails) - 20) . ' more');
            }
        }

        if ($result->failedDetails !== []) {
            $this->newLine();
            $this->error('Failed transactions:');
            foreach ($result->failedDetails as $detail) {
                $this->line("  #{$detail['transaction_id']}: {$detail['error']}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
