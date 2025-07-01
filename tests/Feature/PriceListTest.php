<?php

namespace Tests\Feature;

use App\Models\PriceList;
use App\Models\Country;
use App\Models\City;
use App\Models\ServiceType;
use App\Models\ProviderBranch;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_price_list(): void
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $serviceType = ServiceType::factory()->create();

        $priceList = PriceList::create([
            'country_id' => $country->id,
            'city_id' => $city->id,
            'service_type_id' => $serviceType->id,
            'day_price' => 150.00,
            'weekend_price' => 180.00,
            'night_weekday_price' => 200.00,
            'night_weekend_price' => 250.00,
            'suggested_markup' => 1.25,
            'final_price_notes' => 'Test pricing',
        ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'day_price' => 150.00,
            'suggested_markup' => 1.25,
        ]);
    }

    public function test_price_list_relationships(): void
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $serviceType = ServiceType::factory()->create();
        $provider = Provider::factory()->create(['country_id' => $country->id]);
        $providerBranch = ProviderBranch::factory()->create([
            'provider_id' => $provider->id,
            'city_id' => $city->id,
        ]);

        $priceList = PriceList::create([
            'country_id' => $country->id,
            'city_id' => $city->id,
            'service_type_id' => $serviceType->id,
            'provider_branch_id' => $providerBranch->id,
            'day_price' => 150.00,
        ]);

        $this->assertInstanceOf(Country::class, $priceList->country);
        $this->assertInstanceOf(City::class, $priceList->city);
        $this->assertInstanceOf(ServiceType::class, $priceList->serviceType);
        $this->assertInstanceOf(ProviderBranch::class, $priceList->providerBranch);
    }

    public function test_display_name_attribute(): void
    {
        $country = Country::factory()->create(['name' => 'Test Country']);
        $city = City::factory()->create(['name' => 'Test City', 'country_id' => $country->id]);
        $serviceType = ServiceType::factory()->create(['name' => 'Test Service']);

        $priceList = PriceList::create([
            'country_id' => $country->id,
            'city_id' => $city->id,
            'service_type_id' => $serviceType->id,
            'day_price' => 150.00,
        ]);

        $this->assertEquals('Test Country - Test City - Test Service', $priceList->display_name);
    }

    public function test_can_filter_by_country(): void
    {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        $city1 = City::factory()->create(['country_id' => $country1->id]);
        $city2 = City::factory()->create(['country_id' => $country2->id]);
        $serviceType = ServiceType::factory()->create();

        PriceList::create([
            'country_id' => $country1->id,
            'city_id' => $city1->id,
            'service_type_id' => $serviceType->id,
            'day_price' => 150.00,
        ]);

        PriceList::create([
            'country_id' => $country2->id,
            'city_id' => $city2->id,
            'service_type_id' => $serviceType->id,
            'day_price' => 200.00,
        ]);

        $filtered = PriceList::forCountry($country1->id)->get();
        $this->assertCount(1, $filtered);
        $this->assertEquals($country1->id, $filtered->first()->country_id);
    }
}
