<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceFileFeeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceFileFeeServiceTest extends TestCase
{
    private InvoiceFileFeeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceFileFeeService;
    }

    #[Test]
    public function determine_tier_uses_configured_thresholds(): void
    {
        $this->assertSame('simple', $this->service->determineTier(349.99));
        $this->assertSame('middle', $this->service->determineTier(350));
        $this->assertSame('middle', $this->service->determineTier(999.99));
        $this->assertSame('complex', $this->service->determineTier(1000));
        $this->assertNull($this->service->determineTier(0));
    }

    #[Test]
    public function calculate_multiplier_units_uses_cap(): void
    {
        $this->assertSame(0, $this->service->calculateMultiplierUnits(0));
        $this->assertSame(1, $this->service->calculateMultiplierUnits(100));
        $this->assertSame(1, $this->service->calculateMultiplierUnits(350));
        $this->assertSame(2, $this->service->calculateMultiplierUnits(351));
        $this->assertSame(3, $this->service->calculateMultiplierUnits(900));
    }

    #[Test]
    public function calculate_bill_items_total_excludes_file_fee_lines(): void
    {
        $invoice = new Invoice(['id' => 1]);
        $invoice->setRelation('items', collect([
            new InvoiceItem(['item_type' => InvoiceItem::TYPE_BILL, 'amount' => 200]),
            new InvoiceItem(['item_type' => InvoiceItem::TYPE_BILL, 'amount' => 100]),
            new InvoiceItem(['item_type' => InvoiceItem::TYPE_FILE_FEE, 'amount' => 50]),
        ]));

        $this->assertSame(300.0, $this->service->calculateBillItemsTotal($invoice));
    }
}
