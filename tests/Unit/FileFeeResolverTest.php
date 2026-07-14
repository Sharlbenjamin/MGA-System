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
    public function it_resolves_tier_amount_for_matching_country_from_package(): void
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
            'simple_amount' => 85,
            'middle_amount' => 150,
            'complex_amount' => 250,
        ]);
        $fee->countries()->sync([$country->id]);

        FileFee::create([
            'simple_amount' => 50,
            'middle_amount' => 100,
            'complex_amount' => 200,
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
            'simple_amount' => 50,
            'middle_amount' => 150,
            'complex_amount' => 300,
        ]);

        $clientFee = FileFee::create([
            'simple_amount' => 60,
            'middle_amount' => 175,
            'complex_amount' => 325,
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
            null,
            $clientId,
        ));

        $this->assertSame(150.0, $this->resolver->resolveTierAmount(
            FileFee::TIER_MIDDLE,
            $country->id,
            null,
            99999,
        ));
    }

    #[Test]
    public function it_prefers_city_specific_fee_over_country_only_fee(): void
    {
        $country = Country::create([
            'iso' => 'ES',
            'name' => 'Spain',
            'nicename' => 'Spain',
            'iso3' => 'ESP',
            'numcode' => 724,
            'phonecode' => 34,
        ]);

        $cityId = (int) DB::table('cities')->insertGetId([
            'name' => 'Madrid',
            'country_id' => $country->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $countryFee = FileFee::create([
            'simple_amount' => 40,
            'middle_amount' => 120,
            'complex_amount' => 220,
        ]);
        $countryFee->countries()->sync([$country->id]);

        $cityFee = FileFee::create([
            'simple_amount' => 55,
            'middle_amount' => 140,
            'complex_amount' => 240,
        ]);
        $cityFee->countries()->sync([$country->id]);
        DB::table('file_fee_city')->insert([
            'file_fee_id' => $cityFee->id,
            'city_id' => $cityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(140.0, $this->resolver->resolveTierAmount(
            FileFee::TIER_MIDDLE,
            $country->id,
            $cityId,
        ));

        $this->assertSame(120.0, $this->resolver->resolveTierAmount(
            FileFee::TIER_MIDDLE,
            $country->id,
            null,
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
    public function it_does_not_match_tier_packages_for_service_type_lookup(): void
    {
        FileFee::create([
            'simple_amount' => 50,
            'middle_amount' => 100,
            'complex_amount' => 200,
        ]);

        $serviceType = ServiceType::create(['name' => 'Telemedicine']);

        $this->assertNull($this->resolver->resolveServiceTypeAmount($serviceType->id));
    }

    #[Test]
    public function it_returns_tier_package_for_caps(): void
    {
        $country = Country::create([
            'iso' => 'DE',
            'name' => 'Germany',
            'nicename' => 'Germany',
            'iso3' => 'DEU',
            'numcode' => 276,
            'phonecode' => 49,
        ]);

        $fee = FileFee::create([
            'simple_amount' => 70,
            'middle_amount' => 130,
            'complex_amount' => 210,
            'simple_max_total' => 400,
            'middle_max_total' => 900,
        ]);
        $fee->countries()->sync([$country->id]);

        $package = $this->resolver->resolveTierPackage($country->id);

        $this->assertNotNull($package);
        $this->assertSame([
            'simple_max' => 400.0,
            'middle_max' => 900.0,
        ], $package->tierCaps());
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

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('country_id');
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
            $table->string('name')->nullable();
            $table->string('tier', 16)->nullable();
            $table->foreignId('service_type_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('simple_amount', 10, 2)->nullable();
            $table->decimal('middle_amount', 10, 2)->nullable();
            $table->decimal('complex_amount', 10, 2)->nullable();
            $table->decimal('simple_max_total', 10, 2)->nullable();
            $table->decimal('middle_max_total', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('file_fee_country', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_fee_id');
            $table->foreignId('country_id');
            $table->timestamps();
            $table->unique(['file_fee_id', 'country_id']);
        });

        Schema::create('file_fee_city', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_fee_id');
            $table->foreignId('city_id');
            $table->timestamps();
            $table->unique(['file_fee_id', 'city_id']);
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
