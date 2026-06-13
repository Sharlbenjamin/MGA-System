<?php

namespace App\Observers;

use App\Models\TransactionAttachment;
use App\Services\TransactionDocumentationService;

class TransactionAttachmentObserver
{
    public function __construct(
        protected TransactionDocumentationService $documentationService
    ) {}

    public function created(TransactionAttachment $attachment): void
    {
        if ($attachment->transaction) {
            $this->documentationService->syncAndRecalculate($attachment->transaction);
        }
    }

    public function deleted(TransactionAttachment $attachment): void
    {
        if ($attachment->transaction) {
            $this->documentationService->syncAndRecalculate($attachment->transaction);
        }
    }
}
