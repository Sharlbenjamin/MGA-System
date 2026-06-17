<?php

namespace App\Filament\Support;

use App\Imports\TransactionExcelImporter;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\TransactionImportBatch;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImportBankTransactions
{
    public function __construct(
        protected TransactionImportService $importService,
        protected TransactionDocumentationService $documentationService,
    ) {}

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     total_rows: int,
     *     skipped_existing: int,
     *     skipped_in_file: int,
     *     format: string
     * }
     */
    public function parseUploadedFile(string $storagePath, ?int $bankAccountId = null): array
    {
        $fullPath = storage_path('app/'.ltrim($storagePath, '/'));
        $rawRows = TransactionExcelImporter::loadRows($fullPath);
        $classified = $this->importService->classifyRows($rawRows, $bankAccountId);

        $format = \App\Imports\SantanderMovimientosImport::detect($fullPath) ? 'santander' : 'internal';

        $rows = $classified['new']
            ->map(fn (array $row) => $this->importService->normalizeImportRow($row))
            ->filter(fn (array $row) => filled($row['transaction_date']) && $row['amount'] !== null)
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'total_rows' => $rawRows->count(),
            'skipped_existing' => $classified['duplicates_existing']->count(),
            'skipped_in_file' => $classified['duplicates_in_file']->count(),
            'format' => $format,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{batch: TransactionImportBatch, created_ids: array<int, int>, skipped: int}
     */
    public function createTransactions(
        array $rows,
        int $bankAccountId,
        string $filename,
        int $totalRows = 0,
        int $skippedDuplicates = 0,
    ): array {
        $createdIds = [];
        $skipped = 0;
        $userId = Auth::id();

        $batch = DB::transaction(function () use (
            $rows,
            $bankAccountId,
            $filename,
            $totalRows,
            $skippedDuplicates,
            $userId,
            &$createdIds,
            &$skipped,
        ) {
            $batch = TransactionImportBatch::create([
                'filename' => $filename,
                'imported_by' => $userId,
                'total_rows' => $totalRows ?: count($rows),
                'imported_count' => 0,
                'skipped_duplicates' => $skippedDuplicates,
                'status' => 'completed',
            ]);

            $count = 0;

            foreach ($rows as $row) {
                if ($this->importService->isDuplicate($row, $row['type'] ?? null, $bankAccountId)) {
                    $skipped++;

                    continue;
                }

                $date = $this->importService->parseDate($row['transaction_date'] ?? null);
                $amount = $this->importService->parseAmount($row['amount'] ?? null);
                $type = $row['type'] ?? 'Income';

                if (! $date || $amount === null || empty($type)) {
                    $skipped++;

                    continue;
                }

                $transaction = Transaction::create([
                    'name' => 'Imported transaction',
                    'bank_account_id' => $bankAccountId,
                    'related_type' => $row['related_type'] ?? $this->importService->defaultRelatedType($type),
                    'related_id' => null,
                    'amount' => $amount,
                    'type' => $type,
                    'date' => $date,
                    'notes' => $row['description'] ?? null,
                    'reference' => $row['reference'] ?? $row['description'] ?? null,
                    'status' => 'Draft',
                    'documentation_status' => 'incomplete',
                    'import_batch_id' => $batch->id,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $transaction->name = 'TRX-'.$transaction->id;
                $transaction->saveQuietly();

                $this->documentationService->syncAndRecalculate($transaction);
                $createdIds[] = $transaction->id;
                $count++;
            }

            $batch->update(['imported_count' => $count]);

            return $batch;
        });

        return [
            'batch' => $batch,
            'created_ids' => $createdIds,
            'skipped' => $skipped,
        ];
    }

    public static function defaultBankAccountId(): ?int
    {
        return BankAccount::query()->where('type', 'Internal')->value('id');
    }

    public static function dedupeSummaryText(array $data): string
    {
        if (empty($data['rows']) && empty($data['total_rows'])) {
            return 'Upload a file to preview new transactions and skipped duplicates.';
        }

        $formatLabel = ($data['format'] ?? '') === 'santander'
            ? 'Santander MovimientosCuenta'
            : 'Internal bank export';

        return sprintf(
            "Format: %s\nTotal rows in file: %d\nNew to import: %d\nSkipped (already in system): %d\nSkipped (duplicate in file): %d",
            $formatLabel,
            $data['total_rows'] ?? 0,
            count($data['rows'] ?? []),
            $data['skipped_existing'] ?? 0,
            $data['skipped_in_file'] ?? 0,
        );
    }
}
