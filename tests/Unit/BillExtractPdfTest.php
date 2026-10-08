<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\City;
use App\Models\Country;
use App\Models\File;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Province;
use App\Support\BillExtractPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class BillExtractPdfTest extends TestCase
{
    public function test_plain_extract_includes_bill_details_and_omits_the_generated_name(): void
    {
        $bill = $this->bill([
            'name' => 'MG030WH-Bill-01',
            'bill_date' => '2026-04-12',
            'discount' => 10,
            'total_amount' => 155,
        ]);

        $html = view('pdf.bill-extract', BillExtractPdf::data($bill))->render();

        $this->assertStringNotContainsString('MG030WH-Bill-01', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('St. James Hospital', $html);
        $this->assertStringContainsString('12 Harbor Street', $html);
        $this->assertStringContainsString('Valletta, Northern', $html);
        $this->assertStringContainsString('Malta', $html);
        $this->assertStringContainsString('12/04/2026', $html);
        $this->assertStringContainsString('Consultation', $html);
        $this->assertStringContainsString('€120.00', $html);
        $this->assertStringContainsString('Lab tests', $html);
        $this->assertStringContainsString('€45.00', $html);
        $this->assertStringContainsString('€10.00', $html);
        $this->assertStringContainsString('€155.00', $html);

        $pdf = Pdf::loadView('pdf.bill-extract', BillExtractPdf::data($bill))->output();
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_extract_prints_a_custom_bill_name(): void
    {
        $bill = $this->bill([
            'name' => 'April clinic invoice',
            'bill_date' => '2026-04-12',
            'discount' => 0,
            'total_amount' => 165,
        ]);

        $html = view('pdf.bill-extract', BillExtractPdf::data($bill))->render();

        $this->assertStringContainsString('April clinic invoice', $html);
        $this->assertSame('April clinic invoice.pdf', BillExtractPdf::filename($bill));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function bill(array $attributes): Bill
    {
        $country = new Country(['name' => 'Malta']);
        $provider = new Provider(['name' => 'St. James Hospital']);
        $provider->setRelation('country', $country);

        $city = new City(['name' => 'Valletta']);
        $province = new Province(['name' => 'Northern']);
        $branch = new ProviderBranch(['address' => '12 Harbor Street']);
        $branch->setRelation('city', $city);
        $branch->setRelation('province', $province);

        $file = new File(['mga_reference' => 'MG030WH']);

        $bill = new Bill($attributes);
        $bill->setRelation('file', $file);
        $bill->setRelation('provider', $provider);
        $bill->setRelation('branch', $branch);
        $bill->setRelation('items', collect([
            new BillItem(['description' => 'Consultation', 'amount' => 120, 'discount' => 10, 'tax' => 0]),
            new BillItem(['description' => 'Lab tests', 'amount' => 45, 'discount' => 0, 'tax' => 0]),
        ]));

        return $bill;
    }
}
