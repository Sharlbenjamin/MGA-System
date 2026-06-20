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
}
