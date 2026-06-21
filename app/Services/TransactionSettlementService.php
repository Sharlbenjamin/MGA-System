<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Single entry point for post-write documentation settlement on transactions.
 * Pivot-only updates (invoices/bills) must call syncDocumentation() explicitly
 * because they do not fire Transaction model events.
 */
class TransactionSettlementService
{
    public function __construct(
        protected TransactionDocumentationService $documentationService,
    ) {}

    public function syncDocumentation(Transaction $transaction): Transaction
    {
        return $this->documentationService->syncAndRecalculate($transaction);
    }
}
