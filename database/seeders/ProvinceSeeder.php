<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            ['name' => 'Málaga'],
            ['name' => 'Gibraltar'],
            ['name' => 'Almería'],
            ['name' => 'Granada'],
            ['name' => 'Pontevedra'],
            ['name' => 'Sevilla'],
            ['name' => 'Lleida'],
            ['name' => 'Valencia'],
            ['name' => 'Castellón'],
            ['name' => 'Tenerife'],
            ['name' => 'Las Palmas de Gran Canaria'],
            ['name' => 'Madrid'],
            ['name' => 'Vitoria-Gasteiz'],
            ['name' => 'Alicante'],
        ];

        foreach ($provinces as $province) {
            Province::firstOrCreate(
                [
                    'name' => $province['name'],
                    'country_id' => 179,
                ]
            );
        }
    }
}