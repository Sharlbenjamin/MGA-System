<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvidersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Provider::create([
            'country_id' => 179,
            'status' => 'Active',
            'type' => 'Doctor',
            'name' => 'Dr. Helen Ashraf',
        ]);
    }
}
