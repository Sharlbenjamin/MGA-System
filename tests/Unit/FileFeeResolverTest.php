<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\FileFee;
use App\Models\ServiceType;
use App\Services\FileFeeResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileFeeResolverTest extends TestCase
{
    private FileFeeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createSchema();
        $this->resolver = new FileFeeResolver;
    }

    #[Test]
    public function it_resolves_tier_amount_for_matching_country(): void
    {
        $country = Country::create([
            'iso' => 'GB',
            'name' => 'United Kingdom',
            'nicename' => 'United Kingdom',
            'iso3' => 'GBR',
            'numcode' => 826,
            'phonecode' => 44,
        ]);

        $fee = FileFee::create([
            'tier' => FileFee::TIER_SIMPLE,
            'amount' => 85,
        ]);
        $fee->countries()->sync([$country->id]);

        FileFee::create([
            'tier' => FileFee::TIER_SIMPLE,
            'amount' => 50,
        ]);

        $this->assertSame(85.0, $this->resolver->resolveTierAmount(FileFee::TIER_SIMPLE, $country->id));
        $this->assertSame(50.0, $this->resolver->resolveTierAmount(FileFee::TIER_SIMPLE, null));
    }

    #[Test]
    public function it_prefers_client_specific_fee_over_global_fee(): void
    {
        $country = Country::create([
            'iso' => 'FR',
            'name' => 'France',
            'nicename' => 'France',
            'iso3' => 'FRA',
            'numcode' => 250,
            'phonecode' => 33,
        ]);
        $clientId = (int) DB::table('clients')->insertGetId([
            'company_name' => 'Acme Insurance',
            'type' => 'insurance',
            'status' => 'active',
            'initials' => 'AC',
            'invoice_file_fee_strategy' => 'tier',
            'invoice_template' => 'itemized',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        FileFee::create([
            'tier' => FileFee::TIER_MIDDLE,
            'amount' => 150,
        ]);

        $clientFee = FileFee::create([
            'tier' => FileFee::TIER_MIDDLE,
            'amount' => 175,
        ]);
        DB::table('file_fee_client')->insert([
            'file_fee_id' => $clientFee->id,
            'client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(175.0, $this->resolver->resolveTierAmount(
            FileFee::TIER_MIDDLE,
            $country->id,
            $clientId,
        ));

        $this->assertSame(150.0, $this->resolver->resolveTierAmount(
            FileFee::TIER_MIDDLE,
            $country->id,
            99999,
        ));
    }

    #[Test]
    public function it_resolves_service_type_fee_for_multiple_countries(): void
    {
        $serviceType = ServiceType::create(['name' => 'House Call']);
        $countryA = Country::create([
            'iso' => 'FR',
            'name' => 'France',
            'nicename' => 'France',
            'iso3' => 'FRA',
            'numcode' => 250,
            'phonecode' => 33,
        ]);
        $countryB = Country::create([
            'iso' => 'ES',
            'name' => 'Spain',
            'nicename' => 'Spain',
            'iso3' => 'ESP',
            'numcode' => 724,
            'phonecode' => 34,
        ]);

        $fee = FileFee::create([
            'service_type_id' => $serviceType->id,
            'amount' => 120,
        ]);
        $fee->countries()->sync([$countryA->id, $countryB->id]);

        $this->assertSame(120.0, $this->resolver->resolveServiceTypeAmount($serviceType->id, $countryA->id));
        $this->assertSame(120.0, $this->resolver->resolveServiceTypeAmount($serviceType->id, $countryB->id));
        $this->assertNull($this->resolver->resolveServiceTypeAmount($serviceType->id, 99999));
    }

    #[Test]
    public function it_does_not_match_tier_fees_for_service_type_lookup(): void
    {
        FileFee::create([
            'tier' => FileFee::TIER_SIMPLE,
            'amount' => 50,
        ]);

        $serviceType = ServiceType::create(['name' => 'Telemedicine']);

        $this->assertNull($this->resolver->resolveServiceTypeAmount($serviceType->id));
    }

    private function createSchema(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso', 2);
            $table->string('name', 80);
            $table->string('nicename', 80);
            $table->string('iso3', 3)->nullable();
            $table->integer('numcode')->nullable();
            $table->integer('phonecode')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->string('initials')->nullable();
            $table->string('invoice_file_fee_strategy', 32)->default('tier');
            $table->string('invoice_template', 32)->default('itemized');
            $table->timestamps();
        });

        Schema::create('file_fees', function (Blueprint $table) {
            $table->id();
            $table->string('tier', 16)->nullable();
            $table->foreignId('service_type_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::create('file_fee_country', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_fee_id');
            $table->foreignId('country_id');
            $table->timestamps();
            $table->unique(['file_fee_id', 'country_id']);
        });

        Schema::create('file_fee_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_fee_id');
            $table->foreignId('client_id');
            $table->timestamps();
            $table->unique(['file_fee_id', 'client_id']);
        });
    }
}
