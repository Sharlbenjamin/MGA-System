<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Services\TransactionImportService;
use App\Services\TrxOutStatementNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ImportTrxOutTransactions extends Command
{
    protected $signature = 'transactions:import-trx-out
                            {file : Absolute path to TRX Out .xlsx export}
                            {bankAccount? : Bank account ID (optional with --preview)}
                            {--preview : Parse and classify only; skip DB write}
                            {--force-type= : Force all rows to Outflow or Expense}
                            {--force-category= : Force documentation_category on all rows}';

    protected $description = 'Import Santander TRX Out spreadsheet (Type / category / reason columns) into bank transactions';

    public function handle(
        TrxOutStatementNormalizer $normalizer,
        TransactionImportService $importService,
    ): int {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $bankAccountId = $this->argument('bankAccount');
        $previewOnly = (bool) $this->option('preview');

        if ($bankAccountId === null && ! $previewOnly) {
            $this->error('Bank account ID is required unless using --preview.');

            return self::FAILURE;
        }

        if ($bankAccountId !== null && ! BankAccount::query()->whereKey((int) $bankAccountId)->exists()) {
            $this->error("Bank account {$bankAccountId} not found.");

            return self::FAILURE;
        }

        $rows = $normalizer->parseFile($path);

        if ($rows->isEmpty()) {
            $this->error('No rows parsed from the file.');

            return self::FAILURE;
        }

        $rows = $this->applyOverrides($rows);
        $summary = $normalizer->summarize($rows);
        $formulaCategoryRows = $normalizer->countFormulaCategoryRows($rows);

        $this->info('TRX Out statement analysis');
        $this->table(
            ['Metric', 'Value'],
            collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->values()->all(),
        );

        $categoryBreakdown = $rows->countBy(fn (array $row): string => (string) ($row['documentation_category'] ?? 'unknown'));
        $this->newLine();
        $this->info('Documentation category breakdown');
        $this->table(
            ['Category', 'Rows'],
            $categoryBreakdown->map(fn (int $count, string $category) => [$category, (string) $count])->values()->all(),
        );

        $relatedBreakdown = $rows->countBy(fn (array $row): string => (string) ($row['related_type'] ?? 'unknown'));
        $this->newLine();
        $this->info('Related type breakdown');
        $this->table(
            ['Related type', 'Rows'],
            $relatedBreakdown->map(fn (int $count, string $related) => [$related, (string) $count])->values()->all(),
        );

        if ($formulaCategoryRows > 0) {
            $this->newLine();
            $this->warn("{$formulaCategoryRows} row(s) had unevaluated Excel category formulas; categories were inferred from bank Item text.");
        }

        $preview = $bankAccountId !== null
            ? $importService->preview($rows, (int) $bankAccountId)
            : $this->offlinePreview($rows);

        $this->newLine();
        $this->info('Import preview');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Parsed rows', $preview['total']],
                ['Would import', $preview['to_import']],
                ['Skip (already in DB)', $preview['skipped_existing']],
                ['Skip (duplicate in file)', $preview['skipped_in_file']],
                ['Invalid rows', count($preview['errors'])],
            ],
        );

        if ($preview['errors'] !== []) {
            $this->warn('Validation errors:');
            foreach (array_slice($preview['errors'], 0, 10) as $error) {
                $this->line("  - {$error}");
            }
        }

        if ($preview['preview_rows'] !== []) {
            $this->newLine();
            $this->info('Sample rows to import:');
            $this->table(
                ['Row', 'Date', 'Amount', 'Type', 'Category', 'Related', 'Name', 'Comment', 'Reference'],
                collect($preview['preview_rows'])->map(fn (array $row) => [
                    $row['row'] ?? '',
                    $row['date'] ?? '',
                    $row['amount'] ?? '',
                    $row['type'] ?? '',
                    $row['category'] ?? '',
                    $row['related_type'] ?? '',
                    Str::limit((string) ($row['name'] ?? ''), 35),
                    Str::limit((string) ($row['comment'] ?? ''), 20),
                    Str::limit((string) ($row['reference'] ?? ''), 30),
                ])->all(),
            );
        }

        if ($previewOnly) {
            $this->comment('Preview only — no records created.');

            return self::SUCCESS;
        }

        $bankAccountId = (int) $bankAccountId;

        if (! $this->confirm('Import '.$preview['to_import'].' transaction(s) into bank account #'.$bankAccountId.'?', true)) {
            $this->comment('Import cancelled.');

            return self::SUCCESS;
        }

        $result = $importService->import(
            $rows,
            $bankAccountId,
            basename($path),
            null,
            metadataOnly: true,
        );

        $this->newLine();
        $this->info("Import batch #{$result['batch_id']} completed.");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported', $result['imported']],
                ['Skipped (existing)', $result['skipped_existing']],
                ['Skipped (in file)', $result['skipped_in_file']],
                ['Failed', $result['failed']],
            ],
        );

        if ($result['errors'] !== []) {
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                $this->warn($error);
            }
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function offlinePreview(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'to_import' => $rows->count(),
            'skipped_existing' => 0,
            'skipped_in_file' => 0,
            'errors' => [],
            'preview_rows' => $rows->take(10)->map(fn (array $row, int $index): array => [
                'row' => $row['_index'] ?? ($index + 1),
                'date' => $row['transaction_date'] ?? '',
                'amount' => $row['amount'] ?? '',
                'type' => $row['type'] ?? '',
                'category' => $row['documentation_category'] ?? '',
                'related_type' => $row['related_type'] ?? '',
                'name' => $row['name'] ?? '',
                'comment' => $row['notes'] ?? '',
                'reference' => $row['reference'] ?? '',
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function applyOverrides(Collection $rows): Collection
    {
        $forceType = $this->option('force-type');
        $forceCategory = $this->option('force-category');

        if (! filled($forceType) && ! filled($forceCategory)) {
            return $rows;
        }

        return $rows->map(function (array $row) use ($forceType, $forceCategory): array {
            if (filled($forceType)) {
                $row['type'] = ucfirst(strtolower((string) $forceType));
            }

            if (filled($forceCategory)) {
                $row['documentation_category'] = (string) $forceCategory;
            }

            return $row;
        });
    }
}
