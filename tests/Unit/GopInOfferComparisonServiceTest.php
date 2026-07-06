<?php

namespace Tests\Unit;

use App\Models\Gop;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\GopInOfferComparisonService;
use App\Services\GopInOfferService;
use Mockery;
use Tests\TestCase;

class GopInOfferComparisonServiceTest extends TestCase
{

    public function test_it_warns_when_invoice_exceeds_variance_threshold(): void
    {
        $invoice = new Invoice([
            'total_amount' => 255,
        ]);

        $invoice->setRelation('items', collect([
            new InvoiceItem(['item_type' => InvoiceItem::TYPE_BILL, 'amount' => 200]),
            new InvoiceItem(['item_type' => InvoiceItem::TYPE_FILE_FEE, 'amount' => 55]),
        ]));

        $file = new \App\Models\File(['provider_branch_id' => 1]);
        $file->setRelation('providerBranch', (object) ['branch_name' => 'Clinic Rome', 'id' => 1]);
        $invoice->setRelation('file', $file);

        $accepted = new Gop([
            'type' => 'In',
            'status' => Gop::IN_STATUS_ACCEPTED,
            'provider_branch_id' => 1,
            'offered_cost' => 180,
            'file_fee' => 40,
            'amount' => 220,
        ]);
        $accepted->setRelation('providerBranch', (object) ['branch_name' => 'Clinic Rome']);

        $offerService = Mockery::mock(GopInOfferService::class);
        $offerService->shouldReceive('acceptedOfferForFile')->once()->andReturn($accepted);

        $comparison = (new GopInOfferComparisonService($offerService))->compare($invoice);

        $this->assertTrue($comparison['has_accepted']);
        $this->assertSame('warning', $comparison['severity']);
        $this->assertSame(35.0, $comparison['delta']['total']);
    }
}
