<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\Transaction;
use App\Services\TransactionDocumentationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionDocumentationServiceTest extends TestCase
{
    private TransactionDocumentationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionDocumentationService;
    }

    #[Test]
    public function supports_trx_out_pdf_generation_only_for_provider_bulk(): void
    {
        $bulk = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'provider_bulk',
        ]);

        $single = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'provider_single',
        ]);

        $this->assertTrue($this->service->supportsTrxOutPdfGeneration($bulk));
        $this->assertFalse($this->service->supportsTrxOutPdfGeneration($single));
    }

    private function providerBulkTransaction(array $billAttributes): Transaction
    {
        $bill = new Bill($billAttributes);
        $bill->setRelation('file', null);

        $transaction = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'provider_bulk',
            'related_type' => 'Provider',
            'related_id' => 1,
        ]);

        $transaction->setRelation('bills', collect([$bill]));
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('attachments', collect());

        return $transaction;
    }

    #[Test]
    public function can_generate_trx_out_is_false_when_linked_bill_missing_document(): void
    {
        $transaction = $this->providerBulkTransaction([
            'name' => 'Bill A',
            'bill_document_path' => null,
            'bill_google_link' => null,
        ]);

        $this->assertFalse($this->service->canGenerateTrxOut($transaction));
        $this->assertStringContainsString(
            'missing attachments',
            strtolower($this->service->getTrxOutSkipReason($transaction) ?? '')
        );
    }

    #[Test]
    public function can_generate_trx_out_is_true_when_all_bills_documented(): void
    {
        $billA = new Bill([
            'name' => 'Bill A',
            'bill_document_path' => 'bills/a.pdf',
        ]);
        $billA->setRelation('file', null);
        $billB = new Bill([
            'name' => 'Bill B',
            'bill_google_link' => 'https://drive.google.com/file/d/abc',
        ]);
        $billB->setRelation('file', null);

        $transaction = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'provider_bulk',
            'related_type' => 'Provider',
            'related_id' => 1,
        ]);
        $transaction->setRelation('bills', collect([$billA, $billB]));
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('attachments', collect());

        $this->assertTrue($this->service->canGenerateTrxOut($transaction));
    }

    #[Test]
    public function get_trx_out_blocked_message_lists_undocumented_bill_names(): void
    {
        $transaction = $this->providerBulkTransaction([
            'name' => 'Bill Missing Doc',
            'bill_document_path' => null,
            'bill_google_link' => null,
        ]);

        $message = $this->service->getTrxOutBlockedMessage($transaction);

        $this->assertNotNull($message);
        $this->assertStringContainsString('Bill Missing Doc', $message);
    }

    #[Test]
    public function refunded_payment_category_has_no_missing_tasks(): void
    {
        $transaction = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'refunded_payment',
            'notes' => 'Refunded later in March',
        ]);
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('bills', collect());
        $transaction->setRelation('attachments', collect());

        $this->assertSame([], $this->service->getMissingTasks($transaction));
    }

    #[Test]
    public function is_card_payment_detects_tarjeta_in_notes_reference_or_name(): void
    {
        $this->assertTrue($this->service->isCardPayment(new Transaction([
            'notes' => 'Compra Tarjeta 1234',
        ])));
        $this->assertTrue($this->service->isCardPayment(new Transaction([
            'reference' => 'tarj-abc',
        ])));
        $this->assertTrue($this->service->isCardPayment(new Transaction([
            'name' => 'Payment tarjeta store',
        ])));
        $this->assertFalse($this->service->isCardPayment(new Transaction([
            'notes' => 'Bank transfer only',
        ])));
    }

    #[Test]
    public function expense_card_payment_requires_direct_attachment(): void
    {
        $transaction = new Transaction([
            'type' => 'Expense',
            'documentation_category' => 'expense_payment',
            'notes' => 'Compra Tarjeta 1234',
        ]);
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('bills', collect());
        $transaction->setRelation('attachments', collect());

        $this->assertTrue($this->service->transactionRequiresDirectAttachment($transaction));
        $this->assertFalse($this->service->requiresInvoiceOrBillLink($transaction));

        $tasks = $this->invokeComputeMissingTasks($transaction);
        $pendingKeys = collect($tasks)->where('status', 'pending')->pluck('key')->all();

        $this->assertContains('missing_expense_receipt', $pendingKeys);
    }

    #[Test]
    public function outflow_card_payment_requires_bill_documents_not_transaction_receipt(): void
    {
        $bill = new Bill([
            'name' => 'Provider Bill',
            'bill_document_path' => null,
            'bill_google_link' => null,
        ]);
        $bill->setRelation('file', null);

        $transaction = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'card_provider',
            'related_type' => 'Provider',
            'related_id' => 1,
            'notes' => 'Compra Tarjeta 4176570171221270',
        ]);
        $transaction->setRelation('bills', collect([$bill]));
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('attachments', collect());

        $this->assertTrue($this->service->requiresInvoiceOrBillLink($transaction));
        $this->assertFalse($this->service->transactionRequiresDirectAttachment($transaction));

        $tasks = $this->invokeComputeMissingTasks($transaction);
        $pendingKeys = collect($tasks)->where('status', 'pending')->pluck('key')->all();

        $this->assertContains('missing_bill_documents', $pendingKeys);
        $this->assertNotContains('missing_card_receipt', $pendingKeys);
    }

    #[Test]
    public function legacy_outflow_card_expense_category_uses_bill_docs_via_type_override(): void
    {
        $bill = new Bill([
            'name' => 'Provider Bill',
            'bill_google_link' => 'https://drive.google.com/file/d/abc',
        ]);
        $bill->setRelation('file', null);

        $transaction = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'card_expense',
            'related_type' => 'Provider',
            'related_id' => 1,
            'notes' => 'Compra Tarjeta 4176570171221270',
        ]);
        $transaction->setRelation('bills', collect([$bill]));
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('attachments', collect());

        $tasks = $this->invokeComputeMissingTasks($transaction);
        $pendingKeys = collect($tasks)->where('status', 'pending')->pluck('key')->all();

        $this->assertNotContains('missing_card_receipt', $pendingKeys);
        $this->assertNotContains('missing_expense_receipt', $pendingKeys);
    }

    #[Test]
    public function refunded_payment_with_card_text_in_notes_has_no_missing_tasks(): void
    {
        $transaction = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'refunded_payment',
            'notes' => 'Refund for Tarjeta charge reversed',
        ]);
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('bills', collect());
        $transaction->setRelation('attachments', collect());

        $this->assertSame([], $this->invokeComputeMissingTasks($transaction));
    }

    #[Test]
    public function skipped_income_resolves_to_complete_with_no_pending_tasks(): void
    {
        $transaction = new Transaction([
            'type' => 'Income',
            'documentation_category' => 'client_payment',
        ]);
        $transaction->setRawAttributes(array_merge($transaction->getAttributes(), [
            'documentation_skipped_at' => '2026-06-24 12:00:00',
        ]));
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('bills', collect());
        $transaction->setRelation('attachments', collect());

        $this->assertTrue($this->service->isDocumentationSkipped($transaction));
        $this->assertSame('complete', $this->service->resolveDocumentationStatus($transaction));
        $this->assertSame([], $this->service->getMissingTasks($transaction));
        $this->assertFalse($this->service->hasPendingDocumentTasks($transaction));
        $this->assertSame('Complete (skipped)', $this->service->getDocumentationStatusLabel($transaction));
    }

    #[Test]
    public function skipped_expense_resolves_to_complete_with_no_pending_tasks(): void
    {
        $transaction = new Transaction([
            'type' => 'Expense',
            'documentation_category' => 'expense_payment',
        ]);
        $transaction->setRawAttributes(array_merge($transaction->getAttributes(), [
            'documentation_skipped_at' => '2026-06-24 12:00:00',
        ]));
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('bills', collect());
        $transaction->setRelation('attachments', collect());

        $this->assertTrue($this->service->canSkipDocumentation(
            new Transaction(['type' => 'Expense'])
        ));
        $this->assertFalse($this->service->canSkipDocumentation(
            new Transaction(['type' => 'Outflow'])
        ));
        $this->assertSame('complete', $this->service->resolveDocumentationStatus($transaction));
        $this->assertSame([], $this->service->getMissingTasks($transaction));
    }

    #[Test]
    public function get_form_pending_task_keys_excludes_expense_receipt_for_outflow_with_bills(): void
    {
        $bill = new Bill([
            'name' => 'Provider Bill',
            'bill_google_link' => 'https://drive.google.com/file/d/abc',
        ]);
        $bill->setRelation('file', null);

        $transaction = new Transaction([
            'type' => 'Outflow',
            'documentation_category' => 'provider_single',
            'related_type' => 'Provider',
            'related_id' => 1,
        ]);
        $transaction->setRelation('bills', collect([$bill]));
        $transaction->setRelation('invoices', collect());
        $transaction->setRelation('attachments', collect());

        $pendingKeys = $this->service->getFormPendingTaskKeys($transaction);

        $this->assertNotContains('missing_expense_receipt', $pendingKeys);
        $this->assertNotContains('missing_card_receipt', $pendingKeys);
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, fix_type: string, meta?: array}>
     */
    private function invokeComputeMissingTasks(Transaction $transaction): array
    {
        $method = new \ReflectionMethod($this->service, 'computeMissingTasks');
        $method->setAccessible(true);

        return $method->invoke($this->service, $transaction);
    }
}
