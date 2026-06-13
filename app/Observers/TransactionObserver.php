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
        if ($transaction->wasRecentlyCreated || $transaction->wasChanged([
            'type', 'related_type', 'related_id', 'attachment_path',
            'notes', 'name', 'trx_in_pdf_path', 'trx_out_pdf_path',
        ])) {
            $this->documentationService->syncAndRecalculate($transaction);
        }
    }
}
