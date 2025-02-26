<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\ServiceType;

class ProviderBranchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProviderBranch::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'city' => fake()->city(),
            'status' => fake()->randomElement(["Active","Hold"]),
            'priority' => fake()->numberBetween(-10000, 10000),
            'service_type_id' => ServiceType::factory(),
            'communication_method' => fake()->regexify('[A-Za-z0-9]{50}'),
            'day_cost' => fake()->randomFloat(2, 0, 999999.99),
            'night_cost' => fake()->randomFloat(2, 0, 999999.99),
            'weekend_cost' => fake()->randomFloat(2, 0, 999999.99),
            'weekend_night_cost' => fake()->randomFloat(2, 0, 999999.99),
        ];
    }
}
