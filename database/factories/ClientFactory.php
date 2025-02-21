<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Client;

class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->word(),
            'type' => fake()->randomElement(["Assistance","Insurance","Agency"]),
            'status' => fake()->randomElement(["Searching","Interested","Sent","Rejected","Active","On"]),
            'initials' => fake()->regexify('[A-Za-z0-9]{10}'),
            'number_requests' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
