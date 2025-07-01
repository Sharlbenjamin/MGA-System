<?php

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\Country;
use App\Models\City;
use App\Models\ServiceType;
use App\Models\ProviderBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::inRandomOrder()->first() ?? Country::factory()->create();
        $city = City::where('country_id', $country->id)->inRandomOrder()->first() ?? City::factory()->create(['country_id' => $country->id]);
        $serviceType = ServiceType::inRandomOrder()->first() ?? ServiceType::factory()->create();
        
        // Randomly decide if this should be provider-specific or general pricing
        $hasProviderBranch = $this->faker->boolean(30); // 30% chance of provider-specific pricing
        
        $providerBranch = null;
        if ($hasProviderBranch) {
            $providerBranch = ProviderBranch::where('city_id', $city->id)
                ->where('status', 'Active')
                ->inRandomOrder()
                ->first();
        }
        
        // Generate realistic price ranges
        $basePrice = $this->faker->randomFloat(2, 50, 300);
        $weekendMultiplier = $this->faker->randomFloat(2, 1.1, 1.5);
        $nightMultiplier = $this->faker->randomFloat(2, 1.2, 1.8);
        $nightWeekendMultiplier = $this->faker->randomFloat(2, 1.3, 2.0);
        
        return [
            'country_id' => $country->id,
            'city_id' => $city->id,
            'service_type_id' => $serviceType->id,
            'provider_branch_id' => $providerBranch?->id,
            'day_price' => $basePrice,
            'weekend_price' => round($basePrice * $weekendMultiplier, 2),
            'night_weekday_price' => round($basePrice * $nightMultiplier, 2),
            'night_weekend_price' => round($basePrice * $nightWeekendMultiplier, 2),
            'suggested_markup' => $this->faker->randomFloat(2, 1.15, 1.35),
            'final_price_notes' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    /**
     * Indicate that the price list is for general pricing (no specific provider).
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_branch_id' => null,
        ]);
    }

    /**
     * Indicate that the price list is for a specific provider branch.
     */
    public function providerSpecific(): static
    {
        return $this->state(function (array $attributes) {
            $country = Country::inRandomOrder()->first() ?? Country::factory()->create();
            $city = City::where('country_id', $country->id)->inRandomOrder()->first() ?? City::factory()->create(['country_id' => $country->id]);
            $providerBranch = ProviderBranch::where('city_id', $city->id)
                ->where('status', 'Active')
                ->inRandomOrder()
                ->first();
            
            return [
                'country_id' => $country->id,
                'city_id' => $city->id,
                'provider_branch_id' => $providerBranch?->id,
            ];
        });
    }
}
