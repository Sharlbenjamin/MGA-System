<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrimaryCitiesSeeder extends Seeder
{
    public function run()
    {
        DB::table('cities')->insertOrIgnore([
            // United Kingdom
            ['id' => 1, 'name' => 'London', 'country_id' => 201], // UK
            ['id' => 2, 'name' => 'Birmingham', 'country_id' => 201],
            ['id' => 3, 'name' => 'Manchester', 'country_id' => 201],
            ['id' => 4, 'name' => 'Glasgow', 'country_id' => 201],
            ['id' => 5, 'name' => 'Liverpool', 'country_id' => 201],
            ['id' => 6, 'name' => 'Leeds', 'country_id' => 201],
            ['id' => 7, 'name' => 'Sheffield', 'country_id' => 201],
            ['id' => 8, 'name' => 'Edinburgh', 'country_id' => 201],
            ['id' => 9, 'name' => 'Bristol', 'country_id' => 201],
            ['id' => 10, 'name' => 'Newcastle', 'country_id' => 201],

            // Spain
            ['id' => 11, 'name' => 'Madrid', 'country_id' => 179], // Spain
            ['id' => 12, 'name' => 'Barcelona', 'country_id' => 179],
            ['id' => 13, 'name' => 'Valencia', 'country_id' => 179],
            ['id' => 14, 'name' => 'Seville', 'country_id' => 179],
            ['id' => 15, 'name' => 'Zaragoza', 'country_id' => 179],
            ['id' => 16, 'name' => 'Málaga', 'country_id' => 179],
            ['id' => 17, 'name' => 'Murcia', 'country_id' => 179],
            ['id' => 18, 'name' => 'Bilbao', 'country_id' => 179],
            ['id' => 19, 'name' => 'Alicante', 'country_id' => 179],
            ['id' => 20, 'name' => 'Córdoba', 'country_id' => 179],

            // France
            ['id' => 21, 'name' => 'Paris', 'country_id' => 73], // France
            ['id' => 22, 'name' => 'Marseille', 'country_id' => 73],
            ['id' => 23, 'name' => 'Lyon', 'country_id' => 73],
            ['id' => 24, 'name' => 'Toulouse', 'country_id' => 73],
            ['id' => 25, 'name' => 'Nice', 'country_id' => 73],
            ['id' => 26, 'name' => 'Nantes', 'country_id' => 73],
            ['id' => 27, 'name' => 'Strasbourg', 'country_id' => 73],
            ['id' => 28, 'name' => 'Montpellier', 'country_id' => 73],
            ['id' => 29, 'name' => 'Bordeaux', 'country_id' => 73],
            ['id' => 30, 'name' => 'Lille', 'country_id' => 73],

            // Italy
            ['id' => 31, 'name' => 'Rome', 'country_id' => 94], // Italy
            ['id' => 32, 'name' => 'Milan', 'country_id' => 94],
            ['id' => 33, 'name' => 'Naples', 'country_id' => 94],
            ['id' => 34, 'name' => 'Turin', 'country_id' => 94],
            ['id' => 35, 'name' => 'Palermo', 'country_id' => 94],
            ['id' => 36, 'name' => 'Genoa', 'country_id' => 94],
            ['id' => 37, 'name' => 'Bologna', 'country_id' => 94],
            ['id' => 38, 'name' => 'Florence', 'country_id' => 94],
            ['id' => 39, 'name' => 'Bari', 'country_id' => 94],
            ['id' => 40, 'name' => 'Catania', 'country_id' => 94],
        ]);
    }
}