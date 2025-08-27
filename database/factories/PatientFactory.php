<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Client;
use App\Models\Patient;
use App\Models\Country;

class PatientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'client_id' => Client::factory(),
            'dob' => fake()->date(),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'country_id' => Country::factory(),
        ];
    }
}
