<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Drug;
use App\Models\Prescription;

class DrugFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Drug::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'name' => fake()->name(),
            'pharmaceutical' => fake()->word(),
            'dose' => fake()->word(),
            'duration' => fake()->word(),
        ];
    }
}
