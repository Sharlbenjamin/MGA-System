<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\File;
use App\Models\Gop;
use App\Models\GopItem;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Services\ClientOfferMessageFormatter;
use App\Services\OfferPricingCalculator;
use Tests\TestCase;

class OfferPricingCalculatorTest extends TestCase
{
    private OfferPricingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new OfferPricingCalculator;
    }

    public function test_file_fee_tiers_follow_cost_thresholds(): void
    {
        $this->assertSame('simple', $this->calculator->determineTier(350));
        $this->assertSame('middle', $this->calculator->determineTier(350.01));
        $this->assertSame('middle', $this->calculator->determineTier(1000));
        $this->assertSame('complex', $this->calculator->determineTier(1000.01));
        $this->assertNull($this->calculator->determineTier(0));
    }

    public function test_file_fee_amounts_by_country(): void
    {
        $this->assertSame(85.0, $this->calculator->resolveConfiguredFileFeeAmount('uk', 'simple'));
        $this->assertSame(200.0, $this->calculator->resolveConfiguredFileFeeAmount('uk', 'middle'));
        $this->assertSame(350.0, $this->calculator->resolveConfiguredFileFeeAmount('uk', 'complex'));

        $this->assertSame(70.0, $this->calculator->resolveConfiguredFileFeeAmount('greece', 'simple'));
        $this->assertSame(180.0, $this->calculator->resolveConfiguredFileFeeAmount('greece', 'middle'));
        $this->assertSame(320.0, $this->calculator->resolveConfiguredFileFeeAmount('greece', 'complex'));

        $this->assertSame(50.0, $this->calculator->resolveConfiguredFileFeeAmount('default', 'simple'));
        $this->assertSame(150.0, $this->calculator->resolveConfiguredFileFeeAmount('default', 'middle'));
        $this->assertSame(300.0, $this->calculator->resolveConfiguredFileFeeAmount('default', 'complex'));
    }

    public function test_country_group_from_country_name(): void
    {
        $ukFile = $this->fileWithCountry('UNITED KINGDOM');
        $greeceFile = $this->fileWithCountry('Greece');
        $spainFile = $this->fileWithCountry('Spain');

        $this->assertSame('uk', $this->calculator->resolveCountryGroup($ukFile));
        $this->assertSame('greece', $this->calculator->resolveCountryGroup($greeceFile));
        $this->assertSame('default', $this->calculator->resolveCountryGroup($spainFile));
    }

    public function test_house_visit_doubles_cost_and_ignores_file_fee(): void
    {
        $file = $this->fileWithCountry('Spain', serviceTypeId: 1, serviceName: 'House Call');

        $this->assertSame(300.0, $this->calculator->calculateSellingCost(150, $file, null, 1, 'House Call'));
        $this->assertSame(0.0, $this->calculator->resolveFileFeeAmount($file, 150, 1, 'House Call'));
    }

    public function test_house_visit_uses_system_selling_cost_when_present(): void
    {
        $file = $this->fileWithCountry('Spain', serviceTypeId: 1, serviceName: 'House Call');

        $this->assertSame(280.0, $this->calculator->calculateSellingCost(150, $file, 280, 1, 'House Call'));
    }

    public function test_telemedicine_uses_fixed_selling_unless_cost_exceeds_half(): void
    {
        $uk = $this->fileWithCountry('United Kingdom', serviceTypeId: 2, serviceName: 'Telemedicine');
        $spain = $this->fileWithCountry('Spain', serviceTypeId: 2, serviceName: 'Telemedicine');

        $this->assertSame(85.0, $this->calculator->calculateSellingCost(30, $uk, null, 2, 'Telemedicine'));
        $this->assertSame(86.0, $this->calculator->calculateSellingCost(43, $uk, null, 2, 'Telemedicine'));

        $this->assertSame(75.0, $this->calculator->calculateSellingCost(30, $spain, null, 2, 'Telemedicine'));
        $this->assertSame(80.0, $this->calculator->calculateSellingCost(40, $spain, null, 2, 'Telemedicine'));
        $this->assertSame(0.0, $this->calculator->resolveFileFeeAmount($uk, 30, 2, 'Telemedicine'));
    }

    public function test_other_services_double_cost_and_add_configured_file_fee(): void
    {
        $file = $this->fileWithCountry('Spain', serviceTypeId: 5, serviceName: 'Clinic Visit');

        $this->assertSame(200.0, $this->calculator->calculateSellingCost(100, $file, null, 5, 'Clinic Visit'));
        $this->assertSame(50.0, $this->calculator->resolveFileFeeAmount($file, 200, 5, 'Clinic Visit'));
        $this->assertSame(150.0, $this->calculator->resolveFileFeeAmount($file, 400, 5, 'Clinic Visit'));
        $this->assertSame(300.0, $this->calculator->resolveFileFeeAmount($file, 1200, 5, 'Clinic Visit'));
    }

    public function test_uk_clinic_file_fee_uses_uk_amounts(): void
    {
        $file = $this->fileWithCountry('United Kingdom', serviceTypeId: 5, serviceName: 'Clinic Visit');

        $this->assertSame(85.0, $this->calculator->resolveFileFeeAmount($file, 100, 5, 'Clinic Visit'));
        $this->assertSame(200.0, $this->calculator->resolveFileFeeAmount($file, 400, 5, 'Clinic Visit'));
        $this->assertSame(350.0, $this->calculator->resolveFileFeeAmount($file, 1500, 5, 'Clinic Visit'));
    }

    public function test_with_file_fee_item_appends_fee_for_clinic_and_skips_house_visit(): void
    {
        $clinic = $this->fileWithCountry('Spain', serviceTypeId: 5, serviceName: 'Clinic Visit');
        $house = $this->fileWithCountry('Spain', serviceTypeId: 1, serviceName: 'House Call');

        $clinicItems = $this->calculator->withFileFeeItem([
            ['description' => 'Clinic Visit', 'cost' => 100, 'selling_cost' => 200, 'item_type' => GopItem::TYPE_SERVICE],
        ], $clinic, 5, 'Clinic Visit');

        $this->assertCount(2, $clinicItems);
        $this->assertSame('file_fee', $clinicItems[1]['item_type']);
        $this->assertSame(50.0, $clinicItems[1]['selling_cost']);

        $houseItems = $this->calculator->withFileFeeItem([
            ['description' => 'House Call', 'cost' => 150, 'selling_cost' => 300, 'item_type' => GopItem::TYPE_SERVICE],
        ], $house, 1, 'House Call');

        $this->assertCount(1, $houseItems);
        $this->assertSame(300.0, $this->calculator->totals($houseItems)['total']);
    }

    public function test_client_offer_copy_can_omit_name_and_address(): void
    {
        $file = $this->fileWithCountry('Spain', serviceTypeId: 5, serviceName: 'Clinic Visit');
        $file->mga_reference = 'MGA-1';
        $file->address = 'Patient Street 1';
        $file->setRelation('patient', new \App\Models\Patient(['name' => 'Jane Doe']));
        $file->setRelation('providerBranch', new ProviderBranch(['branch_name' => 'Madrid Clinic', 'address' => 'Calle 1']));

        $gop = new Gop([
            'type' => 'In',
            'offered_cost' => 200,
            'file_fee' => 50,
            'amount' => 250,
            'service_type_id' => 5,
        ]);
        $gop->setRelation('serviceType', new ServiceType(['name' => 'Clinic Visit']));
        $gop->setRelation('providerBranch', $file->providerBranch);
        $gop->setRelation('items', collect([
            new GopItem(['description' => 'Clinic Visit', 'cost' => 100, 'selling_cost' => 200, 'item_type' => GopItem::TYPE_SERVICE]),
        ]));

        $formatter = new ClientOfferMessageFormatter($this->calculator);
        $full = $formatter->formatOffer($file, $gop);
        $withoutIdentity = $formatter->formatOffer($file, $gop, ['provider', 'items', 'total']);

        $this->assertStringContainsString('Patient: Jane Doe', $full);
        $this->assertStringContainsString('Address: Patient Street 1', $full);
        $this->assertStringContainsString('Clinic Visit: 200€', $full);
        $this->assertStringContainsString('File fee: 50€', $full);

        $this->assertStringNotContainsString('Jane Doe', $withoutIdentity);
        $this->assertStringNotContainsString('Patient Street 1', $withoutIdentity);
        $this->assertStringContainsString('Provider: Madrid Clinic', $withoutIdentity);
    }

    public function test_request_copy_lists_items_at_cost_plus_file_fee(): void
    {
        $file = $this->fileWithCountry('Spain', serviceTypeId: 5, serviceName: 'Clinic Visit');
        $file->setRelation('patient', new \App\Models\Patient(['name' => 'Jane Doe']));
        $file->setRelation('providerBranch', new ProviderBranch(['branch_name' => 'Madrid Clinic']));

        $gop = new Gop([
            'type' => 'In',
            'offered_cost' => 200,
            'file_fee' => 50,
            'amount' => 250,
            'service_type_id' => 5,
        ]);
        $gop->setRelation('serviceType', new ServiceType(['name' => 'Clinic Visit']));
        $gop->setRelation('providerBranch', $file->providerBranch);
        $gop->setRelation('items', collect([
            new GopItem(['description' => 'Clinic Visit', 'cost' => 100, 'selling_cost' => 200, 'item_type' => GopItem::TYPE_SERVICE]),
            new GopItem(['description' => 'X-ray', 'cost' => 40, 'selling_cost' => 80, 'item_type' => GopItem::TYPE_SERVICE]),
        ]));

        $message = (new ClientOfferMessageFormatter($this->calculator))->formatRequest($file, $gop, ['items', 'total']);

        $this->assertStringContainsString('Clinic Visit: 100€', $message);
        $this->assertStringContainsString('X-ray: 40€', $message);
        $this->assertStringContainsString('File fee: 50€', $message);
        $this->assertStringContainsString('Total: 190€', $message);
    }

    public function test_house_visit_copy_merges_cost_and_gop(): void
    {
        $file = $this->fileWithCountry('Spain', serviceTypeId: 1, serviceName: 'House Call');
        $file->setRelation('patient', new \App\Models\Patient(['name' => 'Jane Doe']));
        $file->setRelation('providerBranch', new ProviderBranch(['branch_name' => 'Doctor']));

        $gop = new Gop([
            'type' => 'In',
            'offered_cost' => 300,
            'file_fee' => 0,
            'amount' => 300,
            'service_type_id' => 1,
        ]);
        $gop->setRelation('serviceType', new ServiceType(['name' => 'House Call']));
        $gop->setRelation('providerBranch', $file->providerBranch);
        $gop->setRelation('items', collect([
            new GopItem(['description' => 'House Call', 'cost' => 150, 'selling_cost' => 300, 'item_type' => GopItem::TYPE_SERVICE]),
        ]));

        $message = (new ClientOfferMessageFormatter($this->calculator))->formatOffer($file, $gop, ['items', 'total']);

        $this->assertStringContainsString('Cost & GOP: 300€', $message);
        $this->assertStringNotContainsString('File fee', $message);
    }

    protected function fileWithCountry(string $countryName, int $serviceTypeId = 5, string $serviceName = 'Clinic Visit'): File
    {
        $file = new File([
            'service_type_id' => $serviceTypeId,
            'country_id' => 1,
        ]);
        $file->setRelation('country', new Country(['name' => $countryName, 'nicename' => $countryName]));
        $file->setRelation('serviceType', new ServiceType(['id' => $serviceTypeId, 'name' => $serviceName]));
        $file->setRelation('city', null);

        return $file;
    }
}
