<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Str;

class TransactionDocumentationService
{
    public function syncAndRecalculate(Transaction $transaction): Transaction
    {
        $this->syncDerivedFields($transaction);
        $this->recalculateDocumentationStatus($transaction);

        return $transaction->fresh([
            'invoices.file.patient',
            'bills.file.patient',
            'attachments',
            'createdByUser',
            'updatedByUser',
        ]);
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
        $tasks = $this->getMissingTasks($transaction);
        $pending = collect($tasks)->where('status', 'pending');

        if ($pending->isEmpty()) {
            $status = 'complete';
        } elseif ($pending->count() > 1) {
            $status = 'incomplete';
        } else {
            $status = match ($pending->first()['key']) {
                'missing_linked_client', 'missing_linked_provider', 'missing_linked_invoices', 'missing_linked_bills' => 'missing_linked_record',
                'missing_trx_in_pdf', 'missing_trx_out_pdf' => 'missing_generated_pdf',
                default => 'missing_attachment',
            };
        }

        if ($transaction->documentation_status !== $status) {
            $transaction->documentation_status = $status;
            $transaction->saveQuietly();
        }
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

        $tasks = [];

        if ($transaction->type === 'Income') {
            $tasks = array_merge($tasks, $this->incomeTasks($transaction));
        } elseif ($transaction->type === 'Outflow') {
            $tasks = array_merge($tasks, $this->outflowTasks($transaction));
        } elseif ($transaction->type === 'Expense') {
            $tasks = array_merge($tasks, $this->expenseTasks($transaction));
        }

        return $tasks;
    }

    public function getPendingTaskCount(Transaction $transaction): int
    {
        return collect($this->getMissingTasks($transaction))->where('status', 'pending')->count();
    }

    public function getDocumentationLabel(Transaction $transaction): string
    {
        return match (true) {
            $transaction->type === 'Income' => 'Client Payment / Transfer In',
            $transaction->type === 'Expense' => 'Expense',
            $transaction->type === 'Outflow' && $transaction->bills()->exists() => 'Provider Payment / Bulk Transfer Out',
            $transaction->type === 'Outflow' => 'Card Payment',
            default => $transaction->type ?? 'Unknown',
        };
    }

    public function getDirection(Transaction $transaction): string
    {
        return $transaction->type === 'Income' ? 'in' : 'out';
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    protected function incomeTasks(Transaction $transaction): array
    {
        $tasks = [];

        $hasClient = $transaction->related_type === 'Client' && $transaction->related_id;
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

    public function normalizeReference(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::lower(trim($value));

        return $normalized === '' ? null : $normalized;
    }
}
