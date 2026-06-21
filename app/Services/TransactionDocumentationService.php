<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TransactionDocumentationService
{
    private static bool $isSyncing = false;

    private static ?int $syncingTransactionId = null;

    public function syncAndRecalculate(Transaction $transaction): Transaction
    {
        if (self::$isSyncing && self::$syncingTransactionId === $transaction->id) {
            return $transaction;
        }

        self::$isSyncing = true;
        self::$syncingTransactionId = $transaction->id;

        try {
            $this->syncDerivedFields($transaction);
            $this->recalculateDocumentationStatus($transaction);

            $transaction = $transaction->fresh([
                'invoices.file.patient',
                'bills.file.patient',
                'attachments',
                'createdByUser',
                'updatedByUser',
            ]);

            if ($transaction?->bank_account_id) {
                TransactionDocumentationStatsService::forgetBankAccountCache((int) $transaction->bank_account_id);
            }

            return $transaction;
        } finally {
            self::$isSyncing = false;
            self::$syncingTransactionId = null;
        }
    }

    public function syncDerivedFields(Transaction $transaction): void
    {
        if (! $transaction->reference) {
            $transaction->reference = $this->deriveReference($transaction);
        }

        if ($transaction->isDirty('reference')) {
            $transaction->saveQuietly();
        }
    }

    public function deriveReference(Transaction $transaction): ?string
    {
        if ($transaction->name && ! Str::startsWith($transaction->name, 'TRX-')) {
            return Str::limit(trim($transaction->name), 500, '');
        }

        if ($transaction->notes) {
            return Str::limit(trim(Str::before($transaction->notes, "\n")), 500, '');
        }

        return null;
    }

    public function recalculateDocumentationStatus(Transaction $transaction): void
    {
        $status = $this->resolveDocumentationStatus($transaction);

        if ($transaction->documentation_status !== $status) {
            $transaction->documentation_status = $status;
            $transaction->saveQuietly();
        }
    }

    public function resolveDocumentationStatus(Transaction $transaction): string
    {
        $pending = collect($this->getMissingTasks($transaction))->where('status', 'pending');

        if ($pending->isEmpty()) {
            return 'complete';
        }

        $linkKeys = [
            'missing_linked_client',
            'missing_linked_provider',
            'missing_linked_invoices',
            'missing_linked_bills',
        ];

        if ($this->requiresInvoiceOrBillLink($transaction) && ! $this->hasInvoiceOrBillLink($transaction)) {
            return 'unlinked';
        }

        if ($pending->contains(fn (array $task) => in_array($task['key'], $linkKeys, true))) {
            return 'unlinked';
        }

        $attachmentKeys = [
            'missing_card_receipt',
            'missing_expense_receipt',
            'missing_invoice_documents',
            'missing_bill_documents',
        ];

        if ($pending->contains(fn (array $task) => in_array($task['key'], $attachmentKeys, true))) {
            return 'missing_attachment';
        }

        $pdfKeys = ['missing_trx_in_pdf', 'missing_trx_out_pdf'];

        if ($pending->contains(fn (array $task) => in_array($task['key'], $pdfKeys, true))) {
            return 'missing_generated_pdf';
        }

        return 'incomplete';
    }

    public static function isCardPaymentBankText(?string ...$parts): bool
    {
        $text = mb_strtolower(implode(' ', array_filter($parts, fn (?string $part) => filled($part))));

        if ($text === '') {
            return false;
        }

        return str_contains($text, 'tarjeta') || str_contains($text, 'tarj');
    }

    public function isCardTransaction(Transaction $transaction): bool
    {
        if ($transaction->type !== 'Outflow' || $transaction->bills()->exists()) {
            return false;
        }

        return self::isCardPaymentBankText($transaction->notes, $transaction->reference);
    }

    public function requiresInvoiceOrBillLink(Transaction $transaction): bool
    {
        $category = TransactionDocumentationStatsService::resolveCategoryKey($transaction);

        return in_array($category, [
            'client_payment',
            'provider_single',
            'provider_bulk',
            'card_provider',
        ], true);
    }

    public function hasInvoiceOrBillLink(Transaction $transaction): bool
    {
        $category = TransactionDocumentationStatsService::resolveCategoryKey($transaction);

        return match ($category) {
            'client_payment' => $transaction->invoices()->exists(),
            'provider_single', 'provider_bulk', 'card_provider' => $transaction->bills()->exists(),
            default => true,
        };
    }

    public function resolvedCategory(Transaction $transaction): string
    {
        return TransactionDocumentationStatsService::resolveCategoryKey($transaction);
    }

    public function getDocumentationStatusLabel(Transaction $transaction): string
    {
        return $this->formatDocumentationStatusLabel(
            $this->resolveDocumentationStatus($transaction)
        );
    }

    public function formatDocumentationStatusLabel(?string $status): string
    {
        return match ($status) {
            'complete' => 'Complete (ready for taxes)',
            'incomplete' => 'Incomplete',
            'unlinked' => 'Unlinked',
            'missing_attachment' => 'Missing attachment',
            'missing_linked_record' => 'Unlinked',
            'missing_generated_pdf' => 'Missing PDF',
            default => ucfirst(str_replace('_', ' ', $status ?? 'incomplete')),
        };
    }

    public function getDocumentationStatusColor(Transaction $transaction): string
    {
        return self::colorForStatusKey($transaction->documentation_status ?? 'incomplete');
    }

    public static function colorForStatusKey(?string $status): string
    {
        return match ($status) {
            'complete' => 'success',
            'incomplete' => 'warning',
            default => 'danger',
        };
    }

    public function getPendingTaskSummary(Transaction $transaction): ?string
    {
        return $this->getDocumentationColumnDescription($transaction);
    }

    public function getDocumentationColumnSummary(Transaction $transaction): string
    {
        $pending = collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->pluck('label')
            ->map(fn (string $label) => Str::limit(trim($label, '. '), 35));

        if ($pending->isEmpty()) {
            return 'Ready for taxes';
        }

        return $pending->implode('; ');
    }

    public function previewMissingTasksForNewTransaction(string $type): string
    {
        return match ($type) {
            'Income' => 'Missing linked client; No invoices linked; Missing Trx In PDF',
            'Outflow' => 'Missing linked provider; Missing card receipt (until bills linked)',
            'Expense' => 'Missing expense receipt',
            default => 'Incomplete documentation',
        };
    }

    public function getDocumentationColumnDescription(Transaction $transaction): ?string
    {
        $pending = collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->pluck('label')
            ->map(fn (string $label) => trim($label, '. '));

        if ($pending->isEmpty()) {
            return null;
        }

        return 'Still needed: '.$pending->implode('; ');
    }

    public function transactionRequiresDirectAttachment(Transaction $transaction): bool
    {
        $category = $this->resolvedCategory($transaction);

        return in_array($category, ['card_expense', 'expense_payment'], true);
    }

    /**
     * @return array<int, string>
     */
    public function getProofPathLines(Transaction $transaction): array
    {
        $transaction->loadMissing(['invoices', 'bills', 'attachments']);

        $lines = [];

        if ($this->transactionRequiresDirectAttachment($transaction)) {
            if ($transaction->attachment_path) {
                $lines[] = '✓ Transaction attachment';
            } else {
                $lines[] = '⚠ Transaction attachment — not set';
            }
        }

        if ($transaction->attachments->isNotEmpty()) {
            $types = $transaction->attachments->pluck('type')->unique()->implode(', ');
            $lines[] = "✓ Typed attachments: {$types}";
        }

        if ($transaction->type === 'Income') {
            $undocumented = $transaction->invoices->filter(fn (Invoice $invoice) => ! $this->invoiceHasDocument($invoice));
            if ($transaction->invoices->isEmpty()) {
                $lines[] = '⚠ No invoices linked';
            } elseif ($undocumented->isEmpty()) {
                $lines[] = '✓ All linked invoices have documents';
            } else {
                $lines[] = '⚠ '.$undocumented->count().' linked invoice(s) missing documents';
            }

            $lines[] = $transaction->trx_in_pdf_path
                ? '✓ Trx In PDF generated'
                : '⚠ Trx In PDF not generated';
        }

        if ($transaction->type === 'Outflow' && $transaction->bills->isNotEmpty()) {
            $undocumented = $transaction->bills->filter(fn (Bill $bill) => ! $this->billHasDocument($bill));
            if ($undocumented->isEmpty()) {
                $lines[] = '✓ All linked bills have documents';
            } else {
                $lines[] = '⚠ '.$undocumented->count().' linked bill(s) missing documents';
            }

            $lines[] = $transaction->trx_out_pdf_path
                ? '✓ Trx Out PDF generated'
                : '⚠ Trx Out PDF not generated';
        }

        return $lines;
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    public function getMissingTasks(Transaction $transaction): array
    {
        $transaction->loadMissing([
            'invoices.file.patient',
            'bills.file.patient',
            'attachments',
        ]);

        $category = $this->resolvedCategory($transaction);

        return match ($category) {
            'account_feed', 'refund' => [],
            'client_payment' => $this->clientPaymentTasks($transaction),
            'provider_single', 'card_provider' => $this->providerBillTasks($transaction, requireTrxOutPdf: false),
            'provider_bulk' => $this->providerBillTasks($transaction, requireTrxOutPdf: true),
            'card_expense' => $this->receiptTasks($transaction, 'card_receipt', 'Missing card payment receipt.'),
            'expense_payment' => $this->receiptTasks($transaction, 'expense_receipt', 'Missing expense receipt/invoice.'),
            'patient_refund' => $this->receiptTasks($transaction, 'expense_receipt', 'Missing patient refund transfer proof.'),
            'capital_return' => $this->receiptTasks($transaction, 'expense_receipt', 'Missing capital return transfer proof.'),
            default => $this->legacyTasksForType($transaction),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function legacyTasksForType(Transaction $transaction): array
    {
        return match ($transaction->type) {
            'Income' => $this->clientPaymentTasks($transaction),
            'Outflow' => $this->outflowTasks($transaction),
            'Expense' => $this->expenseTasks($transaction),
            default => [],
        };
    }

    public function getPendingTaskCount(Transaction $transaction): int
    {
        return collect($this->getMissingTasks($transaction))->where('status', 'pending')->count();
    }

    public function getDocumentationLabel(Transaction $transaction): string
    {
        return TransactionDocumentationStatsService::categoryLabel(
            $this->resolvedCategory($transaction)
        );
    }

    public function getDirection(Transaction $transaction): string
    {
        return $transaction->type === 'Income' ? 'in' : 'out';
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function clientPaymentTasks(Transaction $transaction): array
    {
        $tasks = [];

        $hasClient = in_array($transaction->related_type, ['Client', 'Patient'], true) && $transaction->related_id;
        $tasks[] = $this->task(
            'missing_linked_client',
            'Missing linked client.',
            $hasClient ? 'done' : 'pending',
            'link_client'
        );

        $invoiceCount = $transaction->invoices->count();
        $tasks[] = $this->task(
            'missing_linked_invoices',
            'No invoices linked to this payment.',
            $invoiceCount > 0 ? 'done' : 'pending',
            'link_invoices'
        );

        $undocumentedInvoices = $transaction->invoices->filter(fn (Invoice $invoice) => ! $this->invoiceHasDocument($invoice));
        $tasks[] = $this->task(
            'missing_invoice_documents',
            'One or more linked invoices are missing attachments.',
            $undocumentedInvoices->isEmpty() || $invoiceCount === 0 ? 'done' : 'pending',
            'invoice_documents',
            ['invoice_ids' => $undocumentedInvoices->pluck('id')->all()]
        );

        $tasks[] = $this->task(
            'missing_trx_in_pdf',
            'Missing generated Trx In PDF.',
            $transaction->trx_in_pdf_path ? 'done' : 'pending',
            'generate_trx_in_pdf'
        );

        return $tasks;
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function providerBillTasks(Transaction $transaction, bool $requireTrxOutPdf): array
    {
        $hasProvider = in_array($transaction->related_type, ['Provider', 'Branch'], true) && $transaction->related_id;
        $tasks = [];
        $tasks[] = $this->task(
            'missing_linked_provider',
            'Missing linked provider.',
            $hasProvider ? 'done' : 'pending',
            'link_provider'
        );

        $billCount = $transaction->bills->count();
        $tasks[] = $this->task(
            'missing_linked_bills',
            'No bills linked to this transfer.',
            $billCount > 0 ? 'done' : 'pending',
            'link_bills'
        );

        $undocumentedBills = $transaction->bills->filter(fn (Bill $bill) => ! $this->billHasDocument($bill));
        $tasks[] = $this->task(
            'missing_bill_documents',
            'One or more linked bills are missing attachments.',
            $undocumentedBills->isEmpty() || $billCount === 0 ? 'done' : 'pending',
            'bill_documents',
            ['bill_ids' => $undocumentedBills->pluck('id')->all()]
        );

        if ($requireTrxOutPdf) {
            $tasks[] = $this->task(
                'missing_trx_out_pdf',
                'Missing generated Trx Out PDF.',
                $transaction->trx_out_pdf_path ? 'done' : 'pending',
                'generate_trx_out_pdf'
            );
        }

        return $tasks;
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string}>
     */
    protected function receiptTasks(Transaction $transaction, string $type, string $label): array
    {
        return [
            $this->task(
                $type === 'card_receipt' ? 'missing_card_receipt' : 'missing_expense_receipt',
                $label,
                $this->hasReceiptAttachment($transaction, $type) ? 'done' : 'pending',
                'upload_receipt'
            ),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function incomeTasks(Transaction $transaction): array
    {
        return $this->clientPaymentTasks($transaction);
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function outflowTasks(Transaction $transaction): array
    {
        $hasBills = $transaction->bills->isNotEmpty();

        if ($hasBills) {
            $hasProvider = in_array($transaction->related_type, ['Provider', 'Branch'], true) && $transaction->related_id;
            $tasks = [];
            $tasks[] = $this->task(
                'missing_linked_provider',
                'Missing linked provider.',
                $hasProvider ? 'done' : 'pending',
                'link_provider'
            );
            $tasks[] = $this->task(
                'missing_linked_bills',
                'No bills linked to this transfer.',
                'done',
                'link_bills'
            );

            $undocumentedBills = $transaction->bills->filter(fn (Bill $bill) => ! $this->billHasDocument($bill));
            $tasks[] = $this->task(
                'missing_bill_documents',
                'One or more linked bills are missing attachments.',
                $undocumentedBills->isEmpty() ? 'done' : 'pending',
                'bill_documents',
                ['bill_ids' => $undocumentedBills->pluck('id')->all()]
            );

            $tasks[] = $this->task(
                'missing_trx_out_pdf',
                'Missing generated Trx Out PDF.',
                $transaction->trx_out_pdf_path ? 'done' : 'pending',
                'generate_trx_out_pdf'
            );

            return $tasks;
        }

        return [
            $this->task(
                'missing_card_receipt',
                'Missing card payment bill/receipt.',
                $this->hasReceiptAttachment($transaction, 'card_receipt') ? 'done' : 'pending',
                'upload_receipt'
            ),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string}>
     */
    protected function expenseTasks(Transaction $transaction): array
    {
        return [
            $this->task(
                'missing_expense_receipt',
                'Missing expense receipt/invoice.',
                $this->hasReceiptAttachment($transaction, 'expense_receipt') ? 'done' : 'pending',
                'upload_receipt'
            ),
        ];
    }

    protected function task(string $key, string $label, string $status, string $fixType, array $meta = []): array
    {
        return array_filter([
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'fix_type' => $fixType,
            'meta' => $meta ?: null,
        ], fn ($value) => $value !== null);
    }

    public function hasReceiptAttachment(Transaction $transaction, string $type): bool
    {
        if ($transaction->attachment_path) {
            return true;
        }

        return $transaction->attachments()->where('type', $type)->exists();
    }

    public function invoiceHasDocument(Invoice $invoice): bool
    {
        return (bool) ($invoice->invoice_document_path || $invoice->invoice_google_link);
    }

    public function billHasDocument(Bill $bill): bool
    {
        return (bool) ($bill->bill_document_path || $bill->bill_google_link);
    }

    public function supportsTrxInPdfGeneration(Transaction $transaction): bool
    {
        return $this->resolvedCategory($transaction) === 'client_payment';
    }

    public function supportsTrxOutPdfGeneration(Transaction $transaction): bool
    {
        return $this->resolvedCategory($transaction) === 'provider_bulk';
    }

    public function undocumentedBillsSummary(Transaction $transaction): string
    {
        $transaction->loadMissing('bills');

        return $transaction->bills
            ->filter(fn (Bill $bill) => ! $this->billHasDocument($bill))
            ->map(fn (Bill $bill) => $bill->name.' — edit bill to upload document')
            ->implode("\n");
    }

    public function undocumentedInvoicesSummary(Transaction $transaction): string
    {
        $transaction->loadMissing('invoices');

        return $transaction->invoices
            ->filter(fn (Invoice $invoice) => ! $this->invoiceHasDocument($invoice))
            ->map(fn (Invoice $invoice) => $invoice->name.' — edit invoice to upload document')
            ->implode("\n");
    }

    public function getTrxInBlockedMessage(Transaction $transaction): ?string
    {
        if (! $this->supportsTrxInPdfGeneration($transaction) || $this->canGenerateTrxIn($transaction)) {
            return null;
        }

        return $this->appendDocumentSummary(
            $this->getTrxInSkipReason($transaction) ?? 'Cannot generate Trx In PDF yet.',
            $transaction,
            'missing_invoice_documents',
        );
    }

    public function getTrxOutBlockedMessage(Transaction $transaction): ?string
    {
        if (! $this->supportsTrxOutPdfGeneration($transaction) || $this->canGenerateTrxOut($transaction)) {
            return null;
        }

        return $this->appendDocumentSummary(
            $this->getTrxOutSkipReason($transaction) ?? 'Cannot generate Trx Out PDF yet.',
            $transaction,
            'missing_bill_documents',
        );
    }

    protected function appendDocumentSummary(string $reason, Transaction $transaction, string $documentTaskKey): string
    {
        $pendingKeys = collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->pluck('key')
            ->all();

        if (! in_array($documentTaskKey, $pendingKeys, true)) {
            return $reason;
        }

        $summary = $documentTaskKey === 'missing_bill_documents'
            ? $this->undocumentedBillsSummary($transaction)
            : $this->undocumentedInvoicesSummary($transaction);

        if ($summary === '') {
            return $reason;
        }

        return $reason."\n\n".$summary;
    }

    public function canGenerateTrxIn(Transaction $transaction): bool
    {
        if ($this->resolvedCategory($transaction) !== 'client_payment') {
            return false;
        }

        $blockingKeys = [
            'missing_linked_client',
            'missing_linked_invoices',
            'missing_invoice_documents',
        ];

        return ! collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->contains(fn (array $task) => in_array($task['key'], $blockingKeys, true));
    }

    public function canGenerateTrxOut(Transaction $transaction): bool
    {
        if ($this->resolvedCategory($transaction) !== 'provider_bulk') {
            return false;
        }

        $blockingKeys = [
            'missing_linked_provider',
            'missing_linked_bills',
            'missing_bill_documents',
        ];

        return ! collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->contains(fn (array $task) => in_array($task['key'], $blockingKeys, true));
    }

    public function getTrxInSkipReason(Transaction $transaction): ?string
    {
        if ($this->resolvedCategory($transaction) !== 'client_payment') {
            return 'Not a Client Payment transaction';
        }

        return $this->firstPendingTaskLabel($transaction, [
            'missing_linked_client',
            'missing_linked_invoices',
            'missing_invoice_documents',
        ]);
    }

    public function getTrxOutSkipReason(Transaction $transaction): ?string
    {
        if ($this->resolvedCategory($transaction) !== 'provider_bulk') {
            return 'Not a Provider Bulk transaction';
        }

        return $this->firstPendingTaskLabel($transaction, [
            'missing_linked_provider',
            'missing_linked_bills',
            'missing_bill_documents',
        ]);
    }

    protected function firstPendingTaskLabel(Transaction $transaction, array $keys): ?string
    {
        $pending = collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->first(fn (array $task) => in_array($task['key'], $keys, true));

        return $pending['label'] ?? null;
    }

    public function normalizeReference(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::lower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<int, string>
     */
    public static function documentTaskKeys(): array
    {
        return [
            'missing_card_receipt',
            'missing_expense_receipt',
            'missing_invoice_documents',
            'missing_bill_documents',
            'missing_trx_in_pdf',
            'missing_trx_out_pdf',
        ];
    }

    public function hasPendingDocumentTasks(Transaction $transaction): bool
    {
        return collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->contains(fn (array $task): bool => in_array($task['key'], self::documentTaskKeys(), true));
    }

    public static function scopeWithPendingDocumentTasks(Builder $query): Builder
    {
        return $query->whereIn('documentation_status', [
            'missing_attachment',
            'missing_generated_pdf',
            'incomplete',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function pendingDocumentTaskLabels(Transaction $transaction): array
    {
        return collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->filter(fn (array $task): bool => in_array($task['key'], self::documentTaskKeys(), true))
            ->pluck('label')
            ->map(fn (string $label): string => trim($label, '. '))
            ->values()
            ->all();
    }
}
