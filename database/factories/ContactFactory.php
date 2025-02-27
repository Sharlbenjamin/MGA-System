<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\City;
use App\Models\Contact;
use App\Models\Contactable;
use App\Models\Country;

class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'contactable_id' => Contactable::factory(),
            'contactable_type' => fake()->word(),
            'type' => fake()->randomElement(["client","provider","branch","patient"]),
            'name' => fake()->name(),
            'title' => fake()->sentence(4),
            'email' => fake()->safeEmail(),
            'second_email' => fake()->word(),
            'phone_number' => fake()->phoneNumber(),
            'second_phone' => fake()->word(),
            'country_id' => Country::factory(),
            'city_id' => City::factory(),
            'address' => fake()->word(),
            'preferred_contact' => fake()->randomElement(["phone","second"]),
            'status' => fake()->randomElement(["active","inactive"]),
        ];
    }
}
