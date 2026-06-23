<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use App\Models\TransactionImportBatch;
use App\Services\TransactionDocumentationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class TransactionImportService
{
    public function __construct(
        protected TransactionImportColumnMap $columnMap = new TransactionImportColumnMap,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parseRowsFromPath(string $absolutePath): Collection
    {
        $import = new class implements \Maatwebsite\Excel\Concerns\ToArray
        {
            public function array(array $array): array
            {
                return $array;
            }
        };

        $sheets = Excel::toArray($import, $absolutePath);
        $rows = collect($sheets[0] ?? []);

        if ($rows->isEmpty()) {
            return collect();
        }

        $headerRow = $rows->first();
        $headerMap = $this->columnMap->mapHeaders(is_array($headerRow) ? $headerRow : []);

        if ($headerMap === []) {
            throw new \InvalidArgumentException('No recognizable column headers found in the first row.');
        }

        if (! array_key_exists('transaction_date', $headerMap)) {
            throw new \InvalidArgumentException('Missing required column: transaction_date (or date / fecha).');
        }

        return $rows->slice(1)
            ->values()
            ->map(function (array $row, int $index) use ($headerMap): array {
                $normalized = $this->columnMap->normalizeRow($row, $headerMap);
                $normalized['_index'] = $index + 2;

                return $normalized;
            })
            ->filter(fn (array $row): bool => $this->rowHasImportableData($row))
            ->values();
    }

    /**
     * @return array{
     *     total: int,
     *     to_import: int,
     *     skipped_existing: int,
     *     skipped_in_file: int,
     *     errors: array<int, string>,
     *     preview_rows: array<int, array<string, mixed>>
     * }
     */
    public function preview(Collection $rows, int $bankAccountId, bool $skipInFileDuplicates = true): array
    {
        $classified = $this->classifyRows($rows, $bankAccountId, $skipInFileDuplicates);

        $errors = [];

        foreach ($classified['invalid'] as $row) {
            $errors[] = 'Row '.($row['_index'] ?? '?').': '.($row['_error'] ?? 'Invalid row');
        }

        return [
            'total' => $rows->count(),
            'to_import' => $classified['new']->count(),
            'skipped_existing' => $classified['duplicates_existing']->count(),
            'skipped_in_file' => $classified['duplicates_in_file']->count(),
            'errors' => $errors,
            'preview_rows' => $classified['new']->take(10)->map(fn (array $row) => [
                'row' => $row['_index'] ?? null,
                'date' => $this->resolveDate($row)?->format('Y-m-d'),
                'amount' => $this->resolveAmount($row),
                'type' => $this->resolveType($row),
                'name' => $row['name'] ?? null,
                'comment' => $row['notes'] ?? null,
                'category' => $row['documentation_category'] ?? null,
                'related_type' => $row['related_type'] ?? null,
                'reference' => $this->resolveReference($row),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{
     *     batch_id: int,
     *     imported: int,
     *     skipped_existing: int,
     *     skipped_in_file: int,
     *     failed: int,
     *     errors: array<int, string>
     * }
     */
    public function import(
        Collection $rows,
        int $bankAccountId,
        string $filename,
        ?int $userId = null,
        bool $metadataOnly = false,
        bool $skipInFileDuplicates = true,
    ): array {
        $userId ??= Auth::id();
        $classified = $this->classifyRows($rows, $bankAccountId, $skipInFileDuplicates);

        $batch = TransactionImportBatch::query()->create([
            'filename' => $filename,
            'imported_by' => $userId,
            'total_rows' => $rows->count(),
            'imported_count' => 0,
            'skipped_duplicates' => $classified['duplicates_existing']->count() + $classified['duplicates_in_file']->count(),
            'status' => 'processing',
            'notes' => null,
        ]);

        $imported = 0;
        $failed = 0;
        $errors = [];
        $statsService = app(TransactionDocumentationStatsService::class);
        $docService = app(TransactionDocumentationService::class);

        foreach ($classified['new'] as $row) {
            try {
                DB::transaction(function () use ($row, $bankAccountId, $batch, $userId, $statsService, $docService, $metadataOnly, &$imported): void {
                    $transaction = null;

                    TransactionDocumentationService::withoutObserverSync(function () use (
                        $row,
                        $bankAccountId,
                        $batch,
                        $userId,
                        $statsService,
                        $metadataOnly,
                        &$transaction,
                    ): void {
                        $transaction = $this->createTransactionFromRow($row, $bankAccountId, $batch->id, $userId, $metadataOnly);

                        if (! $metadataOnly) {
                            $this->applyOptionalLinks($transaction, $row, $statsService);
                        }
                    });

                    $docService->syncAndRecalculate($transaction->fresh());

                    $imported++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Row '.($row['_index'] ?? '?').': '.$e->getMessage();
            }
        }

        $batch->update([
            'imported_count' => $imported,
            'skipped_duplicates' => $classified['duplicates_existing']->count() + $classified['duplicates_in_file']->count(),
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'notes' => $errors !== [] ? implode("\n", array_slice($errors, 0, 20)) : null,
        ]);

        TransactionDocumentationStatsService::forgetBankAccountCache($bankAccountId);

        return [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'skipped_existing' => $classified['duplicates_existing']->count(),
            'skipped_in_file' => $classified['duplicates_in_file']->count(),
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{
     *     new: Collection<int, array<string, mixed>>,
     *     duplicates_existing: Collection<int, array<string, mixed>>,
     *     duplicates_in_file: Collection<int, array<string, mixed>>,
     *     invalid: Collection<int, array<string, mixed>>
     * }
     */
    public function classifyRows(Collection $rows, int $bankAccountId, bool $skipInFileDuplicates = true): array
    {
        $new = collect();
        $duplicatesExisting = collect();
        $duplicatesInFile = collect();
        $invalid = collect();
        $seenKeys = [];

        foreach ($rows as $row) {
            $validationError = $this->validateRow($row);

            if ($validationError !== null) {
                $row['_error'] = $validationError;
                $invalid->push($row);

                continue;
            }

            $key = $this->rowFingerprint($row);

            if ($skipInFileDuplicates && isset($seenKeys[$key])) {
                $duplicatesInFile->push($row);

                continue;
            }

            $seenKeys[$key] = true;

            if ($this->isDuplicate($row, $bankAccountId)) {
                $duplicatesExisting->push($row);

                continue;
            }

            $new->push($row);
        }

        return [
            'new' => $new,
            'duplicates_existing' => $duplicatesExisting,
            'duplicates_in_file' => $duplicatesInFile,
            'invalid' => $invalid,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function validateRow(array $row): ?string
    {
        $date = $this->resolveDate($row);

        if ($date === null) {
            return 'Invalid or missing transaction_date';
        }

        $amount = $this->resolveAmount($row);

        if ($amount === null || $amount <= 0) {
            return 'Missing or invalid amount (debit, credit, or amount column)';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowHasImportableData(array $row): bool
    {
        if (filled($row['transaction_date'] ?? null)) {
            return true;
        }

        if (filled($row['amount'] ?? null) || filled($row['debit'] ?? null) || filled($row['credit'] ?? null)) {
            return true;
        }

        return filled($row['reference'] ?? null) || filled($row['description'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function rowFingerprint(array $row): string
    {
        $date = $this->resolveDate($row)?->format('Y-m-d') ?? '';
        $amount = number_format($this->resolveAmount($row) ?? 0, 2, '.', '');
        $reference = Str::lower(trim($this->resolveReference($row) ?? ''));

        return implode('|', [$date, $amount, $reference]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isDuplicate(array $row, int $bankAccountId): bool
    {
        $date = $this->resolveDate($row);
        $amount = $this->resolveAmount($row);
        $reference = $this->resolveReference($row);

        if ($date === null || $amount === null) {
            return false;
        }

        $query = Transaction::query()
            ->where('bank_account_id', $bankAccountId)
            ->whereDate('date', $date)
            ->where('amount', $amount);

        if (filled($reference)) {
            $query->where(function ($q) use ($reference): void {
                $q->where('reference', $reference)
                    ->orWhere('name', $reference)
                    ->orWhere('notes', 'like', '%'.$reference.'%');
            });
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function createTransactionFromRow(array $row, int $bankAccountId, int $batchId, ?int $userId, bool $metadataOnly = false): Transaction
    {
        $type = $this->resolveType($row);
        $reference = $this->resolveReference($row);
        $description = (string) ($row['description'] ?? $row['notes'] ?? '');
        $isCard = TransactionDocumentationService::isCardPaymentBankText($description, $reference);

        if ($isCard && $type !== 'Expense') {
            $type = 'Outflow';
        }

        $relatedType = $this->resolveRelatedType($row, $type);
        $category = $this->resolveDocumentationCategory($row, $type, $relatedType, $isCard);

        $name = filled($row['name'] ?? null)
            ? (string) $row['name']
            : ($reference ?: Str::limit($description, 255, '') ?: 'Bank import');

        $transaction = Transaction::query()->create([
            'name' => $name,
            'bank_account_id' => $bankAccountId,
            'related_type' => $relatedType,
            'related_id' => $metadataOnly ? null : $this->resolveRelatedId($row, $relatedType),
            'amount' => $this->resolveAmount($row),
            'type' => $type,
            'date' => $this->resolveDate($row),
            'notes' => $row['notes'] ?? $description ?: null,
            'reference' => $reference,
            'attachment_path' => filled($row['receipt_url'] ?? null) ? (string) $row['receipt_url'] : null,
            'bank_charges' => $this->parseDecimal($row['bank_charges'] ?? null) ?? 0,
            'charges_covered_by_client' => $this->parseBoolean($row['charges_covered_by_client'] ?? null),
            'status' => filled($row['payment_status'] ?? null) ? (string) $row['payment_status'] : 'Completed',
            'documentation_category' => $category,
            'documentation_status' => 'incomplete',
            'import_batch_id' => $batchId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if ($isCard && in_array($category, ['card_expense', 'card_provider', 'expense_payment'], true)) {
            app(TransactionDocumentationStatsService::class)->applyCategory($transaction, $category, []);
            $transaction = $transaction->fresh();
        }

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function applyOptionalLinks(Transaction $transaction, array $row, TransactionDocumentationStatsService $statsService): void
    {
        $billIds = $this->resolveBillIds($row);

        if ($billIds !== []) {
            if (filled($row['documentation_category'] ?? null)) {
                $statsService->applyCategory($transaction, (string) $row['documentation_category'], $billIds);
            } else {
                $statsService->syncBills($transaction, $billIds);
            }

            $this->applyBillAmountsPaid($transaction, $row, $billIds);
            $transaction->refresh();
        }

        $invoiceIds = $this->resolveInvoiceIds($row);

        if ($invoiceIds !== []) {
            $statsService->syncInvoicesWithInitialAmounts($transaction, $invoiceIds);
            $this->applyInvoiceAmountsPaid($transaction, $row, $invoiceIds);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $billIds
     */
    protected function applyBillAmountsPaid(Transaction $transaction, array $row, array $billIds): void
    {
        $amounts = $this->parseCommaList($row['bill_amounts_paid'] ?? null);

        if ($amounts === []) {
            return;
        }

        foreach ($billIds as $index => $billId) {
            if (! isset($amounts[$index])) {
                continue;
            }

            $amount = $this->parseDecimal($amounts[$index]);

            if ($amount === null) {
                continue;
            }

            $transaction->bills()->updateExistingPivot($billId, ['amount_paid' => $amount]);
        }

        foreach ($billIds as $billId) {
            Bill::find($billId)?->recalculatePaidAmountFromTransactions();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $invoiceIds
     */
    protected function applyInvoiceAmountsPaid(Transaction $transaction, array $row, array $invoiceIds): void
    {
        $amounts = $this->parseCommaList($row['invoice_amounts_paid'] ?? null);

        if ($amounts === []) {
            return;
        }

        foreach ($invoiceIds as $index => $invoiceId) {
            if (! isset($amounts[$index])) {
                continue;
            }

            $amount = $this->parseDecimal($amounts[$index]);

            if ($amount === null) {
                continue;
            }

            $transaction->updateInvoicePaidAmount(Invoice::findOrFail($invoiceId), $amount);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, int>
     */
    protected function resolveBillIds(array $row): array
    {
        $names = $this->parseCommaList($row['bill_names'] ?? null);

        if ($names === []) {
            return [];
        }

        return Bill::query()
            ->whereIn('name', $names)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, int>
     */
    protected function resolveInvoiceIds(array $row): array
    {
        $numbers = $this->parseCommaList($row['invoice_numbers'] ?? null);

        if ($numbers === []) {
            return [];
        }

        return Invoice::query()
            ->whereIn('name', $numbers)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveRelatedId(array $row, string $relatedType): ?int
    {
        return match ($relatedType) {
            'Client' => Client::query()->where('company_name', $row['client_name'] ?? '')->value('id')
                ?? Client::query()->where('name', $row['client_name'] ?? '')->value('id'),
            'Provider' => Provider::query()->where('name', $row['provider_name'] ?? '')->value('id'),
            'Branch' => ProviderBranch::query()->where('branch_name', $row['branch_name'] ?? '')->value('id')
                ?? ProviderBranch::query()->where('name', $row['branch_name'] ?? '')->value('id'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveRelatedType(array $row, string $type): string
    {
        if (filled($row['related_type'] ?? null)) {
            return (string) $row['related_type'];
        }

        return match ($type) {
            'Income' => 'Client',
            'Outflow' => filled($row['branch_name'] ?? null) ? 'Branch' : 'Provider',
            'Expense' => 'Other',
            default => 'Client',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveDocumentationCategory(array $row, string $type, string $relatedType, bool $isCard): ?string
    {
        if (filled($row['documentation_category'] ?? null)) {
            return (string) $row['documentation_category'];
        }

        if ($isCard) {
            return $type === 'Expense' ? 'expense_payment' : 'card_provider';
        }

        return TransactionDocumentationStatsService::defaultCategoryFor($type, $relatedType);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveType(array $row): string
    {
        $explicit = Str::title(strtolower(trim((string) ($row['type'] ?? ''))));

        if (in_array($explicit, ['Income', 'Outflow', 'Expense'], true)) {
            return $explicit;
        }

        $credit = $this->parseDecimal($row['credit'] ?? null);
        $debit = $this->parseDecimal($row['debit'] ?? null);

        if ($credit !== null && $credit > 0) {
            return 'Income';
        }

        if ($debit !== null && $debit > 0) {
            return 'Outflow';
        }

        return 'Outflow';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveAmount(array $row): ?float
    {
        $amount = $this->parseDecimal($row['amount'] ?? null);

        if ($amount !== null && $amount > 0) {
            return round(abs($amount), 2);
        }

        $credit = $this->parseDecimal($row['credit'] ?? null);
        $debit = $this->parseDecimal($row['debit'] ?? null);

        if ($credit !== null && $credit > 0) {
            return round(abs($credit), 2);
        }

        if ($debit !== null && $debit > 0) {
            return round(abs($debit), 2);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveDate(array $row): ?Carbon
    {
        $value = $row['transaction_date'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value));
            }

            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveReference(array $row): ?string
    {
        $reference = trim((string) ($row['reference'] ?? ''));

        return $reference !== '' ? $reference : null;
    }


    protected function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(['€', ' ', "\xc2\xa0"], '', (string) $value);

        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',') && ! str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        $normalized = preg_replace('/[^\d.\-]/', '', $normalized) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    protected function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * @return array<int, string>
     */
    protected function parseCommaList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    public function resolveUploadedPath(string $storagePath): string
    {
        if (Storage::disk('local')->exists($storagePath)) {
            return Storage::disk('local')->path($storagePath);
        }

        if (Storage::disk('public')->exists($storagePath)) {
            return Storage::disk('public')->path($storagePath);
        }

        throw new \RuntimeException('Uploaded import file not found.');
    }
}
