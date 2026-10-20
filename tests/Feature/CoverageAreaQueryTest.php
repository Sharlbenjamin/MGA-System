<?php

namespace Tests\Feature;

use App\Services\CoverageAreaQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoverageAreaQueryTest extends TestCase
{
    private CoverageAreaQuery $coverage;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('country_id');
        });
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->unsignedBigInteger('country_id');
        });
        Schema::create('provider_branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->string('status');
            $table->boolean('all_country')->default(false);
        });
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('branch_service', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_branch_id');
            $table->unsignedBigInteger('service_type_id');
        });
        Schema::create('branch_cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_branch_id');
            $table->unsignedBigInteger('city_id');
        });

        DB::table('countries')->insert([
            ['id' => 1, 'name' => 'Spain'],
            ['id' => 2, 'name' => 'France'],
        ]);
        DB::table('cities')->insert([
            ['id' => 10, 'name' => 'Madrid', 'country_id' => 1],
            ['id' => 11, 'name' => 'Barcelona', 'country_id' => 1],
            ['id' => 20, 'name' => 'Paris', 'country_id' => 2],
        ]);
        DB::table('service_types')->insert([
            ['id' => 1, 'name' => 'House Call'],
            ['id' => 2, 'name' => 'Telemedicine'],
            ['id' => 4, 'name' => 'Dental Clinic'],
        ]);
        DB::table('providers')->insert([
            ['id' => 1, 'name' => 'Clinic One', 'status' => 'Active', 'country_id' => 1],
            ['id' => 2, 'name' => 'Dental Co', 'status' => 'Active', 'country_id' => 1],
            ['id' => 3, 'name' => 'Remote Doc', 'status' => 'Active', 'country_id' => 2],
            ['id' => 4, 'name' => 'Held Clinic', 'status' => 'Hold', 'country_id' => 1],
            ['id' => 5, 'name' => 'French Clinic', 'status' => 'Active', 'country_id' => 2],
        ]);
        DB::table('provider_branches')->insert([
            ['id' => 1, 'provider_id' => 1, 'status' => 'Active', 'all_country' => 0],
            ['id' => 2, 'provider_id' => 2, 'status' => 'Active', 'all_country' => 1],
            ['id' => 3, 'provider_id' => 3, 'status' => 'Active', 'all_country' => 0],
            ['id' => 4, 'provider_id' => 4, 'status' => 'Active', 'all_country' => 0],
            ['id' => 5, 'provider_id' => 1, 'status' => 'Hold', 'all_country' => 1],
            ['id' => 6, 'provider_id' => 5, 'status' => 'Active', 'all_country' => 0],
        ]);
        DB::table('branch_service')->insert([
            ['provider_branch_id' => 1, 'service_type_id' => 1],
            ['provider_branch_id' => 2, 'service_type_id' => 4],
            ['provider_branch_id' => 3, 'service_type_id' => 2],
            ['provider_branch_id' => 4, 'service_type_id' => 1],
            ['provider_branch_id' => 5, 'service_type_id' => 1],
            ['provider_branch_id' => 6, 'service_type_id' => 1],
        ]);
        DB::table('branch_cities')->insert([
            ['provider_branch_id' => 1, 'city_id' => 10],
            ['provider_branch_id' => 2, 'city_id' => 10],
            ['provider_branch_id' => 4, 'city_id' => 11],
            ['provider_branch_id' => 6, 'city_id' => 10],
        ]);

        $this->coverage = new CoverageAreaQuery;
    }

    #[Test]
    public function it_lists_current_city_country_and_telemedicine_coverage(): void
    {
        $rows = $this->coverage->builder('current', 1, null, null)->get()->keyBy(fn ($row) => $row->scope.'|'.$row->city_name.'|'.$row->service_type_name);

        $this->assertTrue($rows->has('city|Madrid|House Call'));
        $this->assertSame('Clinic One', $rows['city|Madrid|House Call']->providers);
        $this->assertSame(1, $rows['city|Madrid|House Call']->branch_count);

        $this->assertTrue($rows->has('country|Entire country|Dental Clinic'));
        $this->assertSame('Dental Co', $rows['country|Entire country|Dental Clinic']->providers);
        $this->assertFalse($rows->has('city|Madrid|Dental Clinic'));
        $this->assertFalse($rows->keys()->contains(fn (string $key) => str_contains($key, 'Barcelona') && str_contains($key, 'Dental')));

        $this->assertTrue($rows->has('global|All locations|Telemedicine'));
        $this->assertSame('Remote Doc', $rows['global|All locations|Telemedicine']->providers);
        $this->assertFalse($rows->contains(fn ($row) => $row->city_name === 'Paris'));
    }

    #[Test]
    public function it_lists_only_uncovered_city_and_service_pairs(): void
    {
        $rows = $this->coverage->builder('missing', 1, null, null)
            ->orderBy('c.name')
            ->orderBy('st.name')
            ->get();

        $this->assertSame([
            'Barcelona|House Call',
        ], $rows->map(fn ($row) => $row->city_name.'|'.$row->service_type_name)->all());
    }

    #[Test]
    public function it_does_not_scan_missing_coverage_until_a_country_or_city_is_chosen(): void
    {
        $this->assertCount(0, $this->coverage->builder('missing', null, null, null)->get());
    }

    #[Test]
    public function it_filters_by_city_and_service_type(): void
    {
        $madrid = $this->coverage->builder('current', 1, 10, 1)->get();

        $this->assertCount(1, $madrid);
        $this->assertSame('Madrid', $madrid->first()->city_name);
        $this->assertSame('House Call', $madrid->first()->service_type_name);

        $barcelonaGap = $this->coverage->builder('missing', 1, 11, 1)->get();

        $this->assertCount(1, $barcelonaGap);
        $this->assertSame('Barcelona', $barcelonaGap->first()->city_name);

        $this->assertCount(0, $this->coverage->builder('missing', 1, 11, 4)->orderBy('c.name')->orderBy('st.name')->get());
        $this->assertCount(0, $this->coverage->builder('missing', 1, null, 2)->orderBy('c.name')->get());
        $this->assertCount(0, $this->coverage->builder('current', 1, 20, null)->orderBy('country_name')->orderBy('city_name')->get());
    }

    #[Test]
    public function a_city_filter_still_shows_countrywide_coverage_for_that_city(): void
    {
        $rows = $this->coverage->builder('current', null, 11, null)->get();

        $this->assertTrue($rows->contains(fn ($row) => $row->scope === 'country' && $row->service_type_name === 'Dental Clinic'));
        $this->assertFalse($rows->contains(fn ($row) => $row->scope === 'city' && $row->service_type_name === 'House Call'));
        $this->assertTrue($rows->contains(fn ($row) => $row->scope === 'global'));
    }
}
