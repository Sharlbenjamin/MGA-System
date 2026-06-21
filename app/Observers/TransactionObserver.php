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
        if (! TransactionDocumentationService::shouldObserverSync($transaction)) {
            return;
        }

        if (! $transaction->wasRecentlyCreated && ! $transaction->wasChanged()) {
            return;
        }

        if (! TransactionDocumentationService::shouldSyncDocumentation($transaction)) {
            return;
        }

        $this->documentationService->syncAndRecalculate($transaction);
    }
}
