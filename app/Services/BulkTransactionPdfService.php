<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BulkPdfResult
{
    public function __construct(
        public int $generated = 0,
        public int $skipped = 0,
        public int $failed = 0,
        /** @var array<int, array{transaction_id: int, reason: string}> */
        public array $skippedDetails = [],
        /** @var array<int, array{transaction_id: int, error: string}> */
        public array $failedDetails = [],
    ) {}

    public function merge(self $other): self
    {
        $this->generated += $other->generated;
        $this->skipped += $other->skipped;
        $this->failed += $other->failed;
        $this->skippedDetails = array_merge($this->skippedDetails, $other->skippedDetails);
        $this->failedDetails = array_merge($this->failedDetails, $other->failedDetails);

        return $this;
    }
}

class BulkTransactionPdfService
{
    public function __construct(
        protected TransactionDocumentationService $documentationService,
        protected GenerateTrxInPdfService $trxInPdfService,
        protected GenerateTrxOutPdfService $trxOutPdfService,
    ) {}

    public function generateForPeriod(
        int $year,
        string $quarter,
        string $scope,
        bool $regenerateExisting = false,
        ?int $bankAccountId = null,
    ): BulkPdfResult {
        [$startDate, $endDate] = TaxExportHelpers::resolvePeriodDates($year, $quarter);

        $query = Transaction::query()
            ->whereHas('bankAccount', fn (Builder $q) => $q->where('type', 'Internal'))
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('id');

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        if ($scope === 'receivables') {
            $query->where('type', 'Income');
        } elseif ($scope === 'bulk_bills') {
            $query->where('type', 'Outflow')->whereHas('bills');
        } else {
            $query->where(function (Builder $q) {
                $q->where('type', 'Income')
                    ->orWhere(function (Builder $outflow) {
                        $outflow->where('type', 'Outflow')->whereHas('bills');
                    });
            });
        }

        $result = new BulkPdfResult();

        $query->chunkById(50, function ($transactions) use (&$result, $scope, $regenerateExisting) {
            $result->merge($this->generateForTransactions($transactions, $scope, $regenerateExisting));
        });

        return $result;
    }

    /**
     * @param  iterable<int, Transaction>|Collection<int, Transaction>  $transactions
     */
    public function generateForTransactions(
        iterable $transactions,
        string $scope = 'both',
        bool $regenerateExisting = false,
    ): BulkPdfResult {
        $result = new BulkPdfResult();

        foreach ($transactions as $transaction) {
            if (in_array($scope, ['receivables', 'both'], true) && $transaction->type === 'Income') {
                $this->processTrxIn($transaction, $regenerateExisting, $result);
            }

            if (in_array($scope, ['bulk_bills', 'both'], true)
                && $transaction->type === 'Outflow'
                && $transaction->bills()->exists()) {
                $this->processTrxOut($transaction, $regenerateExisting, $result);
            }
        }

        return $result;
    }

    protected function processTrxIn(Transaction $transaction, bool $regenerateExisting, BulkPdfResult $result): void
    {
        if ($transaction->trx_in_pdf_path && ! $regenerateExisting) {
            $result->skipped++;
            $result->skippedDetails[] = [
                'transaction_id' => $transaction->id,
                'reason' => 'Trx In PDF already exists',
            ];

            return;
        }

        if (! $this->documentationService->canGenerateTrxIn($transaction)) {
            $result->skipped++;
            $result->skippedDetails[] = [
                'transaction_id' => $transaction->id,
                'reason' => $this->documentationService->getTrxInSkipReason($transaction) ?? 'Prerequisites not met',
            ];

            return;
        }

        try {
            $this->trxInPdfService->generate($transaction);
            $result->generated++;
        } catch (\Throwable $e) {
            Log::error('Bulk Trx In PDF generation failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            $result->failed++;
            $result->failedDetails[] = [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function processTrxOut(Transaction $transaction, bool $regenerateExisting, BulkPdfResult $result): void
    {
        if ($transaction->trx_out_pdf_path && ! $regenerateExisting) {
            $result->skipped++;
            $result->skippedDetails[] = [
                'transaction_id' => $transaction->id,
                'reason' => 'Trx Out PDF already exists',
            ];

            return;
        }

        if (! $this->documentationService->canGenerateTrxOut($transaction)) {
            $result->skipped++;
            $result->skippedDetails[] = [
                'transaction_id' => $transaction->id,
                'reason' => $this->documentationService->getTrxOutSkipReason($transaction) ?? 'Prerequisites not met',
            ];

            return;
        }

        try {
            $this->trxOutPdfService->generate($transaction);
            $result->generated++;
        } catch (\Throwable $e) {
            Log::error('Bulk Trx Out PDF generation failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            $result->failed++;
            $result->failedDetails[] = [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ];
        }
    }
}
