<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TransactionDocumentationService
{
    /**
     * When true, getMissingTasks() is not computed and documentation status is not
     * recalculated on save — uses the stored documentation_status column instead.
     * Set to false to re-enable the full checklist pipeline.
     */
    public const MISSING_TASKS_ON_HOLD = false;

    /** @var list<string> */
    public const DOCUMENTATION_SYNC_ATTRIBUTES = [
        'type',
        'related_type',
        'related_id',
        'documentation_category',
        'documentation_skipped_at',
        'documentation_skipped_by',
        'documentation_skip_reason',
        'attachment_path',
        'trx_in_pdf_path',
        'trx_out_pdf_path',
        'name',
        'notes',
        'status',
    ];

    private static bool $isSyncing = false;

    private static ?int $syncingTransactionId = null;

    private static bool $suppressObserverSync = false;

    /** @var array<int, true> */
    private static array $deferredSyncTransactionIds = [];

    /** @var array<int, array{key: string, tasks: array<int, array<string, mixed>>}> */
    private static array $missingTasksCache = [];

    public static function withoutObserverSync(callable $callback): mixed
    {
        self::$suppressObserverSync = true;

        try {
            return $callback();
        } finally {
            self::$suppressObserverSync = false;
        }
    }

    public static function deferSyncFor(int $transactionId): void
    {
        self::$deferredSyncTransactionIds[$transactionId] = true;
    }

    public static function clearDeferredSync(int $transactionId): void
    {
        unset(self::$deferredSyncTransactionIds[$transactionId]);
    }

    public static function shouldObserverSync(Transaction $transaction): bool
    {
        if (self::$suppressObserverSync) {
            return false;
        }

        if (isset(self::$deferredSyncTransactionIds[$transaction->id])) {
            return false;
        }

        return true;
    }

    public static function shouldSyncDocumentation(Transaction $transaction): bool
    {
        if ($transaction->wasRecentlyCreated) {
            return true;
        }

        foreach (self::DOCUMENTATION_SYNC_ATTRIBUTES as $attribute) {
            if ($transaction->wasChanged($attribute)) {
                return true;
            }
        }

        return false;
    }

    public function missingTasksOnHold(): bool
    {
        return self::MISSING_TASKS_ON_HOLD;
    }

    public function forgetMissingTasksCache(?Transaction $transaction = null): void
    {
        if ($transaction?->id) {
            unset(self::$missingTasksCache[$transaction->id]);
        } else {
            self::$missingTasksCache = [];
        }
    }

    public function syncAndRecalculate(Transaction $transaction): Transaction
    {
        return $this->runDocumentationSync($transaction, deriveReference: true);
    }

    /**
     * Pivot-only changes (invoice/bill link, paid amount) — skip reference derivation.
     */
    public function syncAfterPivotChange(Transaction $transaction): Transaction
    {
        return $this->runDocumentationSync($transaction, deriveReference: false);
    }

    protected function runDocumentationSync(Transaction $transaction, bool $deriveReference): Transaction
    {
        if (self::$isSyncing && self::$syncingTransactionId === $transaction->id) {
            return $transaction;
        }

        self::$isSyncing = true;
        self::$syncingTransactionId = $transaction->id;

        try {
            if ($deriveReference) {
                $this->syncDerivedFields($transaction);
            }

            if (! $this->missingTasksOnHold()) {
                $this->forgetMissingTasksCache($transaction);

                $previousStatus = $transaction->documentation_status;

                $this->recalculateDocumentationStatus($transaction);

                $transaction->refresh();

                if (
                    $previousStatus !== $transaction->documentation_status
                    && $transaction->bank_account_id
                ) {
                    TransactionDocumentationStatsService::forgetBankAccountCache((int) $transaction->bank_account_id);
                }
            } else {
                $transaction->refresh();
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

    public function recalculateDocumentationStatus(Transaction $transaction, bool $force = false): void
    {
        if (! $force && $this->missingTasksOnHold()) {
            return;
        }

        if ($this->isDocumentationSkipped($transaction)) {
            if ($transaction->documentation_status !== 'complete') {
                $transaction->documentation_status = 'complete';
                $transaction->saveQuietly();
            }

            return;
        }

        $status = $this->resolveDocumentationStatus($transaction);

        if ($transaction->documentation_status !== $status) {
            $transaction->documentation_status = $status;
            $transaction->saveQuietly();
        }
    }

    public function forceRecalculate(Transaction $transaction): Transaction
    {
        if (self::$isSyncing && self::$syncingTransactionId === $transaction->id) {
            return $transaction;
        }

        self::$isSyncing = true;
        self::$syncingTransactionId = $transaction->id;

        try {
            $this->forgetMissingTasksCache($transaction);

            $previousStatus = $transaction->documentation_status;

            $this->recalculateDocumentationStatus($transaction, force: true);

            $transaction->refresh();

            if (
                $previousStatus !== $transaction->documentation_status
                && $transaction->bank_account_id
            ) {
                TransactionDocumentationStatsService::forgetBankAccountCache((int) $transaction->bank_account_id);
            }

            return $transaction;
        } finally {
            self::$isSyncing = false;
            self::$syncingTransactionId = null;
        }
    }

    public function isDocumentationSkipped(Transaction $transaction): bool
    {
        return filled($transaction->getAttributes()['documentation_skipped_at'] ?? null);
    }

    public function canSkipDocumentation(Transaction $transaction): bool
    {
        return in_array($transaction->type, ['Income', 'Expense'], true)
            && ! $this->isDocumentationSkipped($transaction);
    }

    public function skipDocumentation(Transaction $transaction, ?string $reason = null, ?int $userId = null): Transaction
    {
        if (! in_array($transaction->type, ['Income', 'Expense'], true)) {
            throw new \InvalidArgumentException('Only Income and Expense transactions can skip documentation.');
        }

        $transaction->documentation_skipped_at = now();
        $transaction->documentation_skipped_by = $userId;
        $transaction->documentation_skip_reason = filled($reason) ? trim($reason) : null;
        $transaction->documentation_status = 'complete';
        $transaction->save();

        if ($transaction->bank_account_id) {
            TransactionDocumentationStatsService::forgetBankAccountCache((int) $transaction->bank_account_id);
        }

        return $transaction->refresh();
    }

    public function undoSkipDocumentation(Transaction $transaction, ?int $userId = null): Transaction
    {
        $transaction->documentation_skipped_at = null;
        $transaction->documentation_skipped_by = null;
        $transaction->documentation_skip_reason = null;

        if ($userId !== null) {
            $transaction->updated_by = $userId;
        }

        $transaction->save();

        return $this->forceRecalculate($transaction);
    }

    /**
     * @return array<int, string>
     */
    public function getFormPendingTaskKeys(Transaction $transaction): array
    {
        if ($this->isDocumentationSkipped($transaction)) {
            return [];
        }

        $tasks = $this->missingTasksOnHold()
            ? $this->computeMissingTasksForUi($transaction)
            : $this->getMissingTasks($transaction);

        return collect($tasks)
            ->where('status', 'pending')
            ->pluck('key')
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function computeMissingTasksForUi(Transaction $transaction): array
    {
        $transaction->loadMissing(['invoices', 'bills', 'attachments']);

        return $this->computeMissingTasks($transaction);
    }

    public function resolveDocumentationStatus(Transaction $transaction): string
    {
        if ($this->missingTasksOnHold()) {
            return $transaction->documentation_status ?? 'incomplete';
        }

        if ($this->isDocumentationSkipped($transaction)) {
            return 'complete';
        }

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

    public function isCardPayment(Transaction $transaction): bool
    {
        return self::isCardPaymentBankText($transaction->notes, $transaction->reference, $transaction->name);
    }

    public function isCardTransaction(Transaction $transaction): bool
    {
        if ($transaction->type !== 'Outflow' || $transaction->bills()->exists()) {
            return false;
        }

        return $this->isCardPayment($transaction);
    }

    public function requiresInvoiceOrBillLink(Transaction $transaction): bool
    {
        if ($this->isDocumentationSkipped($transaction)) {
            return false;
        }

        if ($this->isCardPayment($transaction) && $transaction->type === 'Outflow') {
            return true;
        }

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
        if ($this->isCardPayment($transaction) && $transaction->type === 'Outflow') {
            return $transaction->bills()->exists();
        }

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
        if ($this->isDocumentationSkipped($transaction)) {
            return 'Complete (skipped)';
        }

        if (filled($transaction->documentation_status)) {
            return $this->formatDocumentationStatusLabel($transaction->documentation_status);
        }

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
        if ($this->missingTasksOnHold()) {
            $status = $transaction->documentation_status ?? 'incomplete';

            return $status === 'complete'
                ? 'Ready for taxes'
                : $this->formatDocumentationStatusLabel($status);
        }

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
        if ($this->missingTasksOnHold()) {
            $status = $transaction->documentation_status ?? 'incomplete';

            return $status === 'complete'
                ? null
                : 'Status: '.$this->formatDocumentationStatusLabel($status);
        }

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
        if ($this->isCardPayment($transaction) && $transaction->type === 'Expense') {
            return true;
        }

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
        if ($this->isDocumentationSkipped($transaction)) {
            return [];
        }

        if ($this->missingTasksOnHold()) {
            return [];
        }

        $cacheKey = $this->missingTasksCacheKey($transaction);

        if ($transaction->id && isset(self::$missingTasksCache[$transaction->id])) {
            $cached = self::$missingTasksCache[$transaction->id];

            if ($cached['key'] === $cacheKey) {
                return $cached['tasks'];
            }
        }

        $tasks = $this->computeMissingTasks($transaction);

        if ($transaction->id) {
            self::$missingTasksCache[$transaction->id] = [
                'key' => $cacheKey,
                'tasks' => $tasks,
            ];
        }

        return $tasks;
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function resolveCardPaymentTasks(Transaction $transaction): array
    {
        return match ($transaction->type) {
            'Expense' => $this->receiptTasks($transaction, 'expense_receipt', 'Missing expense receipt/invoice.'),
            'Outflow' => $this->providerBillTasks($transaction, requireTrxOutPdf: false),
            default => [],
        };
    }

    protected function computeMissingTasks(Transaction $transaction): array
    {
        $transaction->loadMissing(['invoices', 'bills', 'attachments']);

        $category = $this->resolvedCategory($transaction);

        if ($category !== 'refunded_payment' && $this->isCardPayment($transaction)) {
            return $this->resolveCardPaymentTasks($transaction);
        }

        return match ($category) {
            'account_feed', 'refund', 'refunded_payment' => [],
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

    protected function missingTasksCacheKey(Transaction $transaction): string
    {
        return implode('|', [
            $transaction->id ?? 'new',
            $transaction->updated_at?->timestamp ?? 0,
            $transaction->documentation_status ?? '',
            $transaction->documentation_category ?? '',
            $transaction->related_type ?? '',
            (string) ($transaction->related_id ?? ''),
            $transaction->attachment_path ?? '',
            $transaction->trx_in_pdf_path ?? '',
            $transaction->trx_out_pdf_path ?? '',
            (string) $this->relationCountForCache($transaction, 'invoices'),
            (string) $this->relationCountForCache($transaction, 'bills'),
            (string) $this->relationCountForCache($transaction, 'attachments'),
        ]);
    }

    protected function relationCountForCache(Transaction $transaction, string $relation): int
    {
        if ($transaction->relationLoaded($relation)) {
            return $transaction->{$relation}->count();
        }

        return (int) $transaction->{$relation}()->count();
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

        if ($transaction->relationLoaded('attachments')) {
            return $transaction->attachments->contains(fn ($attachment) => $attachment->type === $type);
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
        if ($this->missingTasksOnHold()) {
            $summary = $documentTaskKey === 'missing_bill_documents'
                ? $this->undocumentedBillsSummary($transaction)
                : $this->undocumentedInvoicesSummary($transaction);

            return $summary === '' ? $reason : $reason."\n\n".$summary;
        }

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

        if ($this->missingTasksOnHold()) {
            return $this->hasLinkedClient($transaction)
                && $this->hasLoadedOrExistingInvoices($transaction)
                && $this->allLinkedInvoicesHaveDocuments($transaction);
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

        if ($this->missingTasksOnHold()) {
            return $this->hasLinkedProvider($transaction)
                && $this->hasLoadedOrExistingBills($transaction)
                && $this->allLinkedBillsHaveDocuments($transaction);
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

        if ($this->missingTasksOnHold()) {
            return $this->lightweightTrxInSkipReason($transaction);
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

        if ($this->missingTasksOnHold()) {
            return $this->lightweightTrxOutSkipReason($transaction);
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
        if ($this->isDocumentationSkipped($transaction)) {
            return false;
        }

        if ($this->missingTasksOnHold()) {
            return in_array($transaction->documentation_status ?? 'incomplete', [
                'missing_attachment',
                'missing_generated_pdf',
                'incomplete',
            ], true);
        }

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
        if ($this->missingTasksOnHold()) {
            return [];
        }

        return collect($this->getMissingTasks($transaction))
            ->where('status', 'pending')
            ->filter(fn (array $task): bool => in_array($task['key'], self::documentTaskKeys(), true))
            ->pluck('label')
            ->map(fn (string $label): string => trim($label, '. '))
            ->values()
            ->all();
    }

    protected function hasLinkedClient(Transaction $transaction): bool
    {
        return in_array($transaction->related_type, ['Client', 'Patient'], true) && (bool) $transaction->related_id;
    }

    protected function hasLinkedProvider(Transaction $transaction): bool
    {
        return in_array($transaction->related_type, ['Provider', 'Branch'], true) && (bool) $transaction->related_id;
    }

    protected function hasLoadedOrExistingInvoices(Transaction $transaction): bool
    {
        return $transaction->relationLoaded('invoices')
            ? $transaction->invoices->isNotEmpty()
            : $transaction->invoices()->exists();
    }

    protected function hasLoadedOrExistingBills(Transaction $transaction): bool
    {
        return $transaction->relationLoaded('bills')
            ? $transaction->bills->isNotEmpty()
            : $transaction->bills()->exists();
    }

    protected function allLinkedInvoicesHaveDocuments(Transaction $transaction): bool
    {
        $transaction->loadMissing('invoices');

        return $transaction->invoices->every(fn (Invoice $invoice) => $this->invoiceHasDocument($invoice));
    }

    protected function allLinkedBillsHaveDocuments(Transaction $transaction): bool
    {
        $transaction->loadMissing('bills');

        return $transaction->bills->isNotEmpty()
            && $transaction->bills->every(fn (Bill $bill) => $this->billHasDocument($bill));
    }

    protected function lightweightTrxInSkipReason(Transaction $transaction): ?string
    {
        if (! $this->hasLinkedClient($transaction)) {
            return 'Missing linked client.';
        }

        if (! $this->hasLoadedOrExistingInvoices($transaction)) {
            return 'No invoices linked to this payment.';
        }

        if (! $this->allLinkedInvoicesHaveDocuments($transaction)) {
            return 'One or more linked invoices are missing attachments.';
        }

        return null;
    }

    protected function lightweightTrxOutSkipReason(Transaction $transaction): ?string
    {
        if (! $this->hasLinkedProvider($transaction)) {
            return 'Missing linked provider.';
        }

        if (! $this->hasLoadedOrExistingBills($transaction)) {
            return 'No bills linked to this transfer.';
        }

        if (! $this->allLinkedBillsHaveDocuments($transaction)) {
            return 'One or more linked bills are missing attachments.';
        }

        return null;
    }
}
