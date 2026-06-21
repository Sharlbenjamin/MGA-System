<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionImportBatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrxOutImportDateRepairService
{
    public function __construct(
        protected TrxOutStatementNormalizer $normalizer = new TrxOutStatementNormalizer,
    ) {}

    /**
     * @return array{
     *     batch: TransactionImportBatch,
     *     transaction_count: int,
     *     revision_row_count: int,
     *     already_correct: int,
     *     to_fix: array<int, array{transaction_id: int, row: int, old_date: string, new_date: string, amount: float, name: string}>,
     *     anomalies: array<int, string>,
     *     can_apply: bool
     * }
     */
    public function audit(string $revisionFilePath, int $bankAccountId, ?int $batchId = null, ?string $batchFilename = null): array
    {
        $batch = $this->resolveBatch($batchId, $batchFilename);
        $revisionRows = $this->normalizer->parseFile($revisionFilePath);

        $transactions = Transaction::query()
            ->where('import_batch_id', $batch->id)
            ->where('bank_account_id', $bankAccountId)
            ->orderBy('id')
            ->get();

        $report = $this->buildPairingReport($transactions, $revisionRows);
        $report['batch'] = $batch;

        return $report;
    }

    /**
     * @return array{updated: int, post_audit: array<string, mixed>}
     */
    public function apply(string $revisionFilePath, int $bankAccountId, ?int $batchId = null, ?string $batchFilename = null): array
    {
        $audit = $this->audit($revisionFilePath, $bankAccountId, $batchId, $batchFilename);

        if (! $audit['can_apply']) {
            throw new \RuntimeException('Audit failed — cannot apply date repairs. Fix anomalies first.');
        }

        $updated = 0;

        foreach ($audit['to_fix'] as $fix) {
            Transaction::query()
                ->whereKey($fix['transaction_id'])
                ->update(['date' => $fix['new_date']]);
            $updated++;
        }

        if ($updated > 0) {
            app(TransactionDocumentationStatsService::class)->forgetBankAccountCache($bankAccountId);
        }

        $postAudit = $this->audit($revisionFilePath, $bankAccountId, $batchId, $batchFilename);

        return [
            'updated' => $updated,
            'post_audit' => $postAudit,
        ];
    }

    protected function resolveBatch(?int $batchId, ?string $batchFilename): TransactionImportBatch
    {
        if ($batchId !== null) {
            $batch = TransactionImportBatch::query()->find($batchId);

            if ($batch === null) {
                throw new \InvalidArgumentException("Import batch #{$batchId} not found.");
            }

            return $batch;
        }

        if (! filled($batchFilename)) {
            throw new \InvalidArgumentException('Provide --batch-id or --batch-filename.');
        }

        $batch = TransactionImportBatch::query()
            ->where('filename', 'like', '%'.$batchFilename.'%')
            ->orderByDesc('id')
            ->first();

        if ($batch === null) {
            throw new \InvalidArgumentException("No import batch found matching filename: {$batchFilename}");
        }

        return $batch;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @param  Collection<int, array<string, mixed>>  $revisionRows
     * @return array{
     *     transaction_count: int,
     *     revision_row_count: int,
     *     already_correct: int,
     *     to_fix: array<int, array{transaction_id: int, row: int, old_date: string, new_date: string, amount: float, name: string}>,
     *     anomalies: array<int, string>,
     *     can_apply: bool
     * }
     */
    public function buildPairingReport(Collection $transactions, Collection $revisionRows): array
    {
        $anomalies = [];
        $toFix = [];
        $alreadyCorrect = 0;

        if ($transactions->count() !== $revisionRows->count()) {
            $anomalies[] = sprintf(
                'Row count mismatch: %d transactions in batch vs %d revision rows.',
                $transactions->count(),
                $revisionRows->count(),
            );
        }

        $pairCount = min($transactions->count(), $revisionRows->count());

        for ($index = 0; $index < $pairCount; $index++) {
            /** @var Transaction $transaction */
            $transaction = $transactions[$index];
            $revisionRow = $revisionRows[$index];
            $excelRow = (int) ($revisionRow['_index'] ?? ($index + 2));

            $revisionAmount = round((float) ($revisionRow['amount'] ?? 0), 2);
            $transactionAmount = round((float) $transaction->amount, 2);

            if (abs($revisionAmount - $transactionAmount) > 0.01) {
                $anomalies[] = sprintf(
                    'Row %d (transaction #%d): amount mismatch DB=%s revision=%s.',
                    $excelRow,
                    $transaction->id,
                    number_format($transactionAmount, 2, '.', ''),
                    number_format($revisionAmount, 2, '.', ''),
                );

                continue;
            }

            $correctDate = (string) ($revisionRow['transaction_date'] ?? '');

            if ($correctDate === '') {
                $anomalies[] = sprintf('Row %d (transaction #%d): revision row has no date.', $excelRow, $transaction->id);

                continue;
            }

            $currentDate = $transaction->date instanceof Carbon
                ? $transaction->date->format('Y-m-d')
                : Carbon::parse((string) $transaction->date)->format('Y-m-d');

            if ($currentDate === $correctDate) {
                $alreadyCorrect++;

                continue;
            }

            $toFix[] = [
                'transaction_id' => $transaction->id,
                'row' => $excelRow,
                'old_date' => $currentDate,
                'new_date' => $correctDate,
                'amount' => $transactionAmount,
                'name' => (string) $transaction->name,
            ];
        }

        if ($transactions->count() > $revisionRows->count()) {
            foreach ($transactions->slice($revisionRows->count()) as $transaction) {
                $anomalies[] = sprintf('Transaction #%d has no matching revision row.', $transaction->id);
            }
        }

        if ($revisionRows->count() > $transactions->count()) {
            foreach ($revisionRows->slice($transactions->count()) as $revisionRow) {
                $anomalies[] = sprintf(
                    'Revision row %d has no matching imported transaction.',
                    (int) ($revisionRow['_index'] ?? 0),
                );
            }
        }

        return [
            'transaction_count' => $transactions->count(),
            'revision_row_count' => $revisionRows->count(),
            'already_correct' => $alreadyCorrect,
            'to_fix' => $toFix,
            'anomalies' => $anomalies,
            'can_apply' => $anomalies === [] && $transactions->count() === $revisionRows->count(),
        ];
    }
}
