<?php

namespace App\Console\Commands;

use App\Services\TrxOutImportDateRepairService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RepairTrxOutImportDates extends Command
{
    protected $signature = 'transactions:repair-trx-out-dates
                            {file : Absolute path to TRX Out Revision .xlsx}
                            {bankAccount : Bank account ID the original import used}
                            {--batch-id= : Import batch ID (optional if --batch-filename is set)}
                            {--batch-filename=TRX Out.xlsx : Match import batch by filename}
                            {--dry-run : Audit only — no database changes}
                            {--apply : Apply date fixes after a clean audit}';

    protected $description = 'Audit and repair wrong TRX Out import dates using TRX Out Revision.xlsx';

    public function handle(TrxOutImportDateRepairService $repairService): int
    {
        $path = (string) $this->argument('file');
        $bankAccountId = (int) $this->argument('bankAccount');
        $batchId = filled($this->option('batch-id')) ? (int) $this->option('batch-id') : null;
        $batchFilename = (string) $this->option('batch-filename');
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        if ($apply && $dryRun) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        if (! $apply) {
            $dryRun = true;
        }

        try {
            if ($dryRun) {
                return $this->runAudit($repairService, $path, $bankAccountId, $batchId, $batchFilename);
            }

            return $this->runApply($repairService, $path, $bankAccountId, $batchId, $batchFilename);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function runAudit(
        TrxOutImportDateRepairService $repairService,
        string $path,
        int $bankAccountId,
        ?int $batchId,
        string $batchFilename,
    ): int {
        $audit = $repairService->audit($path, $bankAccountId, $batchId, $batchFilename);

        $this->info('TRX Out date repair audit');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Import batch ID', (string) $audit['batch']->id],
                ['Batch filename', (string) $audit['batch']->filename],
                ['Transactions in batch', (string) $audit['transaction_count']],
                ['Revision rows', (string) $audit['revision_row_count']],
                ['Already correct', (string) $audit['already_correct']],
                ['To fix', (string) count($audit['to_fix'])],
                ['Anomalies', (string) count($audit['anomalies'])],
                ['Can apply', $audit['can_apply'] ? 'yes' : 'no'],
            ],
        );

        if ($audit['to_fix'] !== []) {
            $this->newLine();
            $this->info('Sample rows to fix:');
            $this->table(
                ['Excel row', 'Transaction', 'Old date', 'New date', 'Amount', 'Name'],
                collect($audit['to_fix'])->take(15)->map(fn (array $fix) => [
                    $fix['row'],
                    '#'.$fix['transaction_id'],
                    $fix['old_date'],
                    $fix['new_date'],
                    number_format($fix['amount'], 2),
                    Str::limit($fix['name'], 40),
                ])->all(),
            );

            if (count($audit['to_fix']) > 15) {
                $this->comment('... and '.(count($audit['to_fix']) - 15).' more.');
            }
        }

        if ($audit['anomalies'] !== []) {
            $this->newLine();
            $this->warn('Anomalies (must be zero before apply):');
            foreach (array_slice($audit['anomalies'], 0, 20) as $anomaly) {
                $this->line("  - {$anomaly}");
            }

            if (count($audit['anomalies']) > 20) {
                $this->comment('... and '.(count($audit['anomalies']) - 20).' more.');
            }

            return self::FAILURE;
        }

        if ($audit['can_apply'] && count($audit['to_fix']) > 0) {
            $this->newLine();
            $this->comment('Audit passed. Re-run with --apply to update dates.');
        } elseif ($audit['can_apply']) {
            $this->newLine();
            $this->info('All dates already match the revision file.');
        }

        return self::SUCCESS;
    }

    protected function runApply(
        TrxOutImportDateRepairService $repairService,
        string $path,
        int $bankAccountId,
        ?int $batchId,
        string $batchFilename,
    ): int {
        $preAudit = $repairService->audit($path, $bankAccountId, $batchId, $batchFilename);

        if (! $preAudit['can_apply']) {
            $this->error('Pre-apply audit failed. Run with --dry-run to inspect anomalies.');

            return self::FAILURE;
        }

        if ($preAudit['to_fix'] === []) {
            $this->info('Nothing to update — all dates already match the revision file.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Update '.count($preAudit['to_fix']).' transaction date(s)?', true)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $repairService->apply($path, $bankAccountId, $batchId, $batchFilename);
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $postAudit = $result['post_audit'];

        $this->newLine();
        $this->info("Updated {$result['updated']} transaction date(s).");
        $this->table(
            ['Post-audit check', 'Value'],
            [
                ['Already correct', (string) $postAudit['already_correct']],
                ['Remaining mismatches', (string) count($postAudit['to_fix'])],
                ['Anomalies', (string) count($postAudit['anomalies'])],
                ['100% aligned', count($postAudit['to_fix']) === 0 && $postAudit['anomalies'] === [] ? 'yes' : 'no'],
            ],
        );

        if (count($postAudit['to_fix']) > 0 || $postAudit['anomalies'] !== []) {
            $this->error('Post-audit did not pass — review remaining mismatches manually.');

            return self::FAILURE;
        }

        $this->info('Post-audit passed — all batch dates match the revision file.');

        return self::SUCCESS;
    }
}
