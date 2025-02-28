<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\MedicalReport;
use App\Models\Request;

class MedicalReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MedicalReport::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'status' => fake()->randomElement(["Waiting","Received","Not Sent","Sent"]),
            'request_id' => Request::factory(),
            'complain' => fake()->text(),
            'diagnosis' => fake()->text(),
            'history' => fake()->text(),
            'temperature' => fake()->word(),
            'blood_pressure' => fake()->word(),
            'pulse' => fake()->word(),
            'examination' => fake()->text(),
            'advice' => fake()->text(),
        ];
    }
}
