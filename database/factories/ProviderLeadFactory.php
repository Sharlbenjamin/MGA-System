<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Provider;
use App\Models\ProviderLead;

class ProviderLeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProviderLead::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'city' => fake()->city(),
            'service_types' => fake()->word(),
            'type' => fake()->randomElement(["Doctor","Clinic","Hospital","Dental"]),
            'provider_id' => Provider::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'communication_method' => fake()->regexify('[A-Za-z0-9]{50}'),
            'status' => fake()->randomElement(["Pending"]),
            'last_contact_date' => fake()->date(),
            'comment' => fake()->text(),
        ];
    }
}
