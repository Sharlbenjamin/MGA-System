<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Gop;
use App\Models\Request;

class GopFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Gop::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'type' => fake()->randomElement(["In","Out"]),
            'amount' => fake()->randomFloat(0, 0, 9999999999.),
            'date' => fake()->date(),
        ];
    }
}
