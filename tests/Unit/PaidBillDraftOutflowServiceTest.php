<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\City;
use App\Models\Country;
use App\Models\File;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use App\Services\PaidBillDraftOutflowService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PaidBillDraftOutflowServiceTest extends TestCase
{
    private PaidBillDraftOutflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaidBillDraftOutflowService;
        Carbon::setTestNow(Carbon::parse('2026-08-25'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function name_includes_create_date_provider_location_payment_date_and_amount(): void
    {
        $bill = $this->paidBill();

        $name = $this->service->formatName($bill, now(), 150.5);

        $this->assertSame(
            '25/08/2026 · Clinic Norte · Madrid, Spain · Paid 20/08/2026 · €150.50',
            $name,
        );
    }

    #[Test]
    public function name_falls_back_to_create_date_when_payment_date_is_missing(): void
    {
        $bill = $this->paidBill();
        $bill->setRawAttributes(array_merge($bill->getAttributes(), [
            'payment_date' => null,
        ]), true);

        $name = $this->service->formatName($bill, now(), 80);

        $this->assertStringContainsString('Paid 25/08/2026', $name);
    }

    #[Test]
    public function name_omits_missing_city_or_country(): void
    {
        $bill = $this->paidBill();
        $bill->file->setRelation('city', null);
        $bill->branch->setRelation('city', null);

        $name = $this->service->formatName($bill, now(), 10);

        $this->assertSame(
            '25/08/2026 · Clinic Norte · Spain · Paid 20/08/2026 · €10.00',
            $name,
        );
    }

    #[Test]
    public function notes_list_provider_country_city_dates_and_amount(): void
    {
        $bill = $this->paidBill();

        $notes = $this->service->formatNotes($bill, now(), 150);

        $this->assertStringContainsString('Provider: Clinic Norte', $notes);
        $this->assertStringContainsString('Country: Spain', $notes);
        $this->assertStringContainsString('City: Madrid', $notes);
        $this->assertStringContainsString('Created: 25/08/2026', $notes);
        $this->assertStringContainsString('Payment date: 20/08/2026', $notes);
        $this->assertStringContainsString('Amount: €150.00', $notes);
    }

    #[Test]
    public function location_prefers_file_country_and_city(): void
    {
        $bill = $this->paidBill();
        $bill->provider->setRelation('country', new Country(['name' => 'Portugal']));
        $bill->branch->setRelation('city', new City(['name' => 'Lisbon']));

        [$country, $city] = $this->service->resolveCountryAndCity($bill);

        $this->assertSame('Spain', $country);
        $this->assertSame('Madrid', $city);
    }

    #[Test]
    public function location_falls_back_to_provider_country_and_branch_city(): void
    {
        $bill = $this->paidBill();
        $bill->file->setRelation('country', null);
        $bill->file->setRelation('city', null);

        [$country, $city] = $this->service->resolveCountryAndCity($bill);

        $this->assertSame('Spain', $country);
        $this->assertSame('Madrid', $city);
    }

    #[Test]
    public function attributes_are_a_draft_outflow_linked_to_the_provider(): void
    {
        $bill = $this->paidBill();
        $bill->provider_id = 42;

        $service = new class extends PaidBillDraftOutflowService
        {
            protected function defaultInternalBankAccountId(): ?int
            {
                return 9;
            }

            protected function currentUserId(): ?int
            {
                return null;
            }
        };

        $attributes = $service->buildAttributes($bill, 150, now());

        $this->assertSame('Outflow', $attributes['type']);
        $this->assertSame('Draft', $attributes['status']);
        $this->assertSame('Provider', $attributes['related_type']);
        $this->assertSame(42, $attributes['related_id']);
        $this->assertSame(150.0, $attributes['amount']);
        $this->assertSame('2026-08-25', $attributes['date']);
        $this->assertSame(9, $attributes['bank_account_id']);
        $this->assertSame('provider_single', $attributes['documentation_category']);
        $this->assertSame('incomplete', $attributes['documentation_status']);
    }

    #[Test]
    public function find_linked_outflow_prefers_a_draft_over_a_completed_transaction(): void
    {
        $bill = $this->paidBill();
        $completed = new Transaction(['type' => 'Outflow', 'status' => 'Completed']);
        $draft = new Transaction(['type' => 'Outflow', 'status' => 'Draft']);
        $income = new Transaction(['type' => 'Income', 'status' => 'Completed']);
        $bill->setRelation('transactions', collect([$completed, $income, $draft]));

        $found = $this->service->findLinkedOutflow($bill);

        $this->assertSame($draft, $found);
    }

    #[Test]
    public function find_linked_outflow_returns_completed_when_no_draft_exists(): void
    {
        $bill = $this->paidBill();
        $completed = new Transaction(['type' => 'Outflow', 'status' => 'Completed']);
        $bill->setRelation('transactions', collect([$completed]));

        $this->assertSame($completed, $this->service->findLinkedOutflow($bill));
    }

    private function paidBill(): Bill
    {
        $country = new Country(['name' => 'Spain']);
        $city = new City(['name' => 'Madrid']);

        $provider = new Provider(['name' => 'Clinic Norte']);
        $provider->setRelation('country', $country);

        $branch = new ProviderBranch(['branch_name' => 'Madrid Centro']);
        $branch->setRelation('city', $city);

        $file = new File(['mga_reference' => 'MG001AB']);
        $file->setRelation('country', $country);
        $file->setRelation('city', $city);

        $bill = new Bill([
            'name' => 'MG001AB-Bill-01',
            'status' => 'Paid',
            'total_amount' => 150,
        ]);
        $bill->setRawAttributes(array_merge($bill->getAttributes(), [
            'payment_date' => '2026-08-20',
        ]), true);
        $bill->setRelation('provider', $provider);
        $bill->setRelation('branch', $branch);
        $bill->setRelation('file', $file);
        $bill->setRelation('transactions', collect());

        return $bill;
    }
}
