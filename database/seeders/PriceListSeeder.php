<?php

namespace Database\Seeders;

use App\Models\PriceList;
use App\Models\Country;
use App\Models\City;
use App\Models\ServiceType;
use App\Models\ProviderBranch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PriceListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $countries = Country::all();
        $serviceTypes = ServiceType::all();
        
        if ($countries->isEmpty() || $serviceTypes->isEmpty()) {
            $this->command->warn('No countries or service types found. Please run CountrySeeder and ServiceTypeSeeder first.');
            return;
        }

        $this->command->info('Creating sample price lists...');

        // Create general pricing for each country/city/service combination
        foreach ($countries as $country) {
            $cities = City::where('country_id', $country->id)->get();
            
            foreach ($cities as $city) {
                foreach ($serviceTypes as $serviceType) {
                    // Create general pricing (no specific provider)
                    PriceList::create([
                        'country_id' => $country->id,
                        'city_id' => $city->id,
                        'service_type_id' => $serviceType->id,
                        'provider_branch_id' => null,
                        'day_price' => fake()->randomFloat(2, 80, 250),
                        'weekend_price' => fake()->randomFloat(2, 100, 300),
                        'night_weekday_price' => fake()->randomFloat(2, 120, 350),
                        'night_weekend_price' => fake()->randomFloat(2, 150, 400),
                        'suggested_markup' => fake()->randomFloat(2, 1.20, 1.30),
                        'final_price_notes' => fake()->optional(0.3)->sentence(),
                    ]);

                    // Create provider-specific pricing for some combinations
                    $providerBranches = ProviderBranch::where('city_id', $city->id)
                        ->where('status', 'Active')
                        ->whereJsonContains('service_types', $serviceType->name)
                        ->take(2) // Limit to 2 providers per city/service
                        ->get();

                    foreach ($providerBranches as $branch) {
                        // Use provider's actual costs as base and add markup
                        $baseDayCost = $branch->day_cost ?: fake()->randomFloat(2, 60, 200);
                        $baseWeekendCost = $branch->weekend_cost ?: $baseDayCost * 1.2;
                        $baseNightCost = $branch->night_cost ?: $baseDayCost * 1.4;
                        $baseWeekendNightCost = $branch->weekend_night_cost ?: $baseDayCost * 1.6;
                        
                        $markup = fake()->randomFloat(2, 1.15, 1.35);

                        PriceList::create([
                            'country_id' => $country->id,
                            'city_id' => $city->id,
                            'service_type_id' => $serviceType->id,
                            'provider_branch_id' => $branch->id,
                            'day_price' => round($baseDayCost * $markup, 2),
                            'weekend_price' => round($baseWeekendCost * $markup, 2),
                            'night_weekday_price' => round($baseNightCost * $markup, 2),
                            'night_weekend_price' => round($baseWeekendNightCost * $markup, 2),
                            'suggested_markup' => $markup,
                            'final_price_notes' => "Provider-specific pricing for {$branch->provider->name} - {$branch->branch_name}",
                        ]);
                    }
                }
            }
        }

        $this->command->info('Price lists created successfully!');
    }
}
