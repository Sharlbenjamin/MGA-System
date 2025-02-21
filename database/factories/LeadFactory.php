<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Client;
use App\Models\Lead;

class LeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Lead::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'email' => fake()->safeEmail(),
            'first_name' => fake()->firstName(),
            'status' => fake()->randomElement(["Introduction","Introduction"]),
            'last_contact_date' => fake()->date(),
        ];
    }
}
