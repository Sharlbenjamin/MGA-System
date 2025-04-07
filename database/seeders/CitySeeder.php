<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        // First, get all provinces with their IDs
        $provinces = Province::where('country_id', 179)->pluck('id', 'name');

        $cities = [
            ['province' => 'Málaga', 'name' => 'Málaga'],
            ['province' => 'Málaga', 'name' => 'Málaga'],
            ['province' => 'Málaga', 'name' => 'Vélez Málaga'],
            ['province' => 'Málaga', 'name' => 'Nerja'],
            ['province' => 'Málaga', 'name' => 'Torremolinos'],
            ['province' => 'Málaga', 'name' => 'Fuengirola'],
            ['province' => 'Málaga', 'name' => 'Rincon de la Victoria'],
            ['province' => 'Málaga', 'name' => 'Málaga'],
            ['province' => 'Gibraltar', 'name' => 'Gibraltar'],
            ['province' => 'Málaga', 'name' => 'Estepona'],
            ['province' => 'Almería', 'name' => 'Almeria'],
            ['province' => 'Almería', 'name' => 'El Ejido'],
            ['province' => 'Almería', 'name' => 'Roquetas de Mar'],
            ['province' => 'Granada', 'name' => 'Granada'],
            ['province' => 'Granada', 'name' => 'Granada'],
            ['province' => 'Pontevedra', 'name' => 'Vigo'],
            ['province' => 'Sevilla', 'name' => 'Sevilla'],
            ['province' => 'Sevilla', 'name' => 'Sevilla'],
            ['province' => 'Lleida', 'name' => 'Lleida'],
            ['province' => 'Valencia', 'name' => 'Valencia'],
            ['province' => 'Valencia', 'name' => 'Valencia'],
            ['province' => 'Valencia', 'name' => 'Valencia'],
            ['province' => 'Castellón', 'name' => 'Castellón'],
            ['province' => 'Tenerife', 'name' => 'Santa cruz de tenerife'],
            ['province' => 'Las Palmas de Gran Canaria', 'name' => 'Las Palmas de Gran Canaria'],
            ['province' => 'Madrid', 'name' => 'Madrid'],
            ['province' => 'Málaga', 'name' => 'Benalmádena'],
            ['province' => 'Pontevedra', 'name' => 'Pontevedra'],
            ['province' => 'Vitoria-Gasteiz', 'name' => 'Vitoria-Gasteiz'],
            ['province' => 'Alicante', 'name' => 'Alicante'],
            ['province' => 'Alicante', 'name' => 'Alicante'],
            ['province' => 'Madrid', 'name' => 'Madrid'],
            ['province' => 'Madrid', 'name' => 'Madrid'],
            ['province' => 'Las Palmas de Gran Canaria', 'name' => 'Las Palmas de Gran Canaria'],
            ['province' => 'Valencia', 'name' => 'Alzira'],
        ];

        foreach ($cities as $cityData) {
            if (isset($provinces[$cityData['province']])) {
                City::firstOrCreate(
                    [
                        'name' => $cityData['name'],
                        'province_id' => $provinces[$cityData['province']],
                        'country_id' => 179,
                    ]
                );
            } else {
                $this->command->warn("Province not found: {$cityData['province']} for city {$cityData['name']}");
            }
        }
    }
}