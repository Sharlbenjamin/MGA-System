<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\TransactionDocumentationService;

class TransactionObserver
{
    public function __construct(
        protected TransactionDocumentationService $documentationService
    ) {}

    public function saved(Transaction $transaction): void
    {
        if ($transaction->wasRecentlyCreated || $transaction->wasChanged()) {
            $this->documentationService->syncAndRecalculate($transaction);
        }
    }
}
