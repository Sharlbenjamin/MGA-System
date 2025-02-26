<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('service_types')->insertOrIgnore([
            ['id' => 1, 'name' => 'House Call'],
            ['id' => 2, 'name' => 'Telemedicine'],
            ['id' => 3, 'name' => 'Hospital Visit'],
            ['id' => 4, 'name' => 'Dental Clinic'],
            ['id' => 5, 'name' => 'Clinic Visit'],
        ]);
    }
}
