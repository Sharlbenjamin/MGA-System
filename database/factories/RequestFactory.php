<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\City;
use App\Models\Country;
use App\Models\Patient;
use App\Models\ProviderBranch;
use App\Models\Providers,nullable;
use App\Models\Request;
use App\Models\ServiceType;

class RequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Request::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(["New","Handling","In Progress","Assisted","Hold","Void"]),
            'mga_reference' => fake()->word(),
            'patient_id' => Patient::factory(),
            'client_reference' => fake()->word(),
            'country_id' => Country::factory(),
            'city_id' => City::factory(),
            'service_type_id' => ServiceType::factory(),
            'provider_id' => Providers,nullable::factory(),
            'provider_branch_id' => ProviderBranch::factory(),
            'service_date' => fake()->date(),
            'service_time' => fake()->time(),
            'address' => fake()->word(),
            'symptoms' => fake()->text(),
            'diagnosis' => fake()->text(),
        ];
    }
}
