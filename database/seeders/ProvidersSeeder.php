<?php

namespace Database\Seeders;

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
        DB::table('providers')->insertOrIgnore([
            'country_id' => 73,
            'status' => 'Potential',
            'type' => 'Agency',
            'name' => 'Urgence Doctors',
            'payment_due' => 30,
            'payment_method' => 'Online Link', // Must match ENUM definition exactly
            'comment' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
