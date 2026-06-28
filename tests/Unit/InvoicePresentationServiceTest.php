<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoicePresentationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicePresentationServiceTest extends TestCase
{
    #[Test]
    public function itemized_template_returns_all_stored_lines(): void
    {
        $client = new Client(['invoice_template' => Client::INVOICE_TEMPLATE_ITEMIZED]);
        $patient = new \App\Models\Patient();
        $patient->setRelation('client', $client);

        $file = new \App\Models\File();
        $file->setRelation('patient', $patient);

        $invoice = new Invoice(['id' => 1]);
        $invoice->setRelation('file', $file);
        $invoice->setRelation('items', collect([
            new InvoiceItem(['description' => 'Bill line', 'amount' => 200, 'item_type' => InvoiceItem::TYPE_BILL]),
            new InvoiceItem(['description' => 'File Fee', 'amount' => 50, 'item_type' => InvoiceItem::TYPE_FILE_FEE]),
        ]));

        $lines = app(InvoicePresentationService::class)->linesForDisplay($invoice);

        $this->assertCount(2, $lines);
        $this->assertSame(200.0, $lines[0]['amount']);
        $this->assertSame(50.0, $lines[1]['amount']);
    }

    #[Test]
    public function combined_template_merges_bill_and_file_fee_lines(): void
    {
        $client = new Client(['invoice_template' => Client::INVOICE_TEMPLATE_COMBINED]);
        $patient = new \App\Models\Patient();
        $patient->setRelation('client', $client);

        $file = new \App\Models\File(['service_date' => now()]);
        $file->setRelation('patient', $patient);

        $invoice = new Invoice(['id' => 1]);
        $invoice->setRelation('file', $file);
        $invoice->setRelation('items', collect([
            new InvoiceItem(['description' => 'Bill line', 'amount' => 200, 'item_type' => InvoiceItem::TYPE_BILL]),
            new InvoiceItem(['description' => 'File Fee', 'amount' => 50, 'item_type' => InvoiceItem::TYPE_FILE_FEE]),
        ]));

        $lines = app(InvoicePresentationService::class)->linesForDisplay($invoice);

        $this->assertCount(1, $lines);
        $this->assertSame(250.0, $lines[0]['amount']);
        $this->assertStringContainsString('Medical assistance services', $lines[0]['description']);
    }

    #[Test]
    public function combined_template_client_disallows_bill_attachment(): void
    {
        $client = new Client(['invoice_template' => Client::INVOICE_TEMPLATE_COMBINED]);
        $this->assertFalse($client->allowsBillAttachmentWithInvoice());

        $clientItemized = new Client(['invoice_template' => Client::INVOICE_TEMPLATE_ITEMIZED]);
        $this->assertTrue($clientItemized->allowsBillAttachmentWithInvoice());
    }
}
