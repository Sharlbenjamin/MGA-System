<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrimaryCitiesSeeder extends Seeder
{
    public function run()
    {
        DB::table('cities')->insert([
            // United Kingdom
            ['name' => 'London', 'country_id' => 201], // UK
            ['name' => 'Birmingham', 'country_id' => 201],
            ['name' => 'Manchester', 'country_id' => 201],
            ['name' => 'Glasgow', 'country_id' => 201],
            ['name' => 'Liverpool', 'country_id' => 201],
            ['name' => 'Leeds', 'country_id' => 201],
            ['name' => 'Sheffield', 'country_id' => 201],
            ['name' => 'Edinburgh', 'country_id' => 201],
            ['name' => 'Bristol', 'country_id' => 201],
            ['name' => 'Newcastle', 'country_id' => 201],

            // Spain
            ['name' => 'Madrid', 'country_id' => 179], // Spain
            ['name' => 'Barcelona', 'country_id' => 179],
            ['name' => 'Valencia', 'country_id' => 179],
            ['name' => 'Seville', 'country_id' => 179],
            ['name' => 'Zaragoza', 'country_id' => 179],
            ['name' => 'Málaga', 'country_id' => 179],
            ['name' => 'Murcia', 'country_id' => 179],
            ['name' => 'Bilbao', 'country_id' => 179],
            ['name' => 'Alicante', 'country_id' => 179],
            ['name' => 'Córdoba', 'country_id' => 179],

            // France
            ['name' => 'Paris', 'country_id' => 73], // France
            ['name' => 'Marseille', 'country_id' => 73],
            ['name' => 'Lyon', 'country_id' => 73],
            ['name' => 'Toulouse', 'country_id' => 73],
            ['name' => 'Nice', 'country_id' => 73],
            ['name' => 'Nantes', 'country_id' => 73],
            ['name' => 'Strasbourg', 'country_id' => 73],
            ['name' => 'Montpellier', 'country_id' => 73],
            ['name' => 'Bordeaux', 'country_id' => 73],
            ['name' => 'Lille', 'country_id' => 73],

            // Italy
            ['name' => 'Rome', 'country_id' => 94], // Italy
            ['name' => 'Milan', 'country_id' => 94],
            ['name' => 'Naples', 'country_id' => 94],
            ['name' => 'Turin', 'country_id' => 94],
            ['name' => 'Palermo', 'country_id' => 94],
            ['name' => 'Genoa', 'country_id' => 94],
            ['name' => 'Bologna', 'country_id' => 94],
            ['name' => 'Florence', 'country_id' => 94],
            ['name' => 'Bari', 'country_id' => 94],
            ['name' => 'Catania', 'country_id' => 94],
        ]);
    }
}