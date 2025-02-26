<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProviderLeadsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('provider_leads')->insert([
            'provider_id' => 1,
            'name' => 'Charles Benjamin',
            'city_id' => 3,
            'service_types' => "House Call",
            'type' => 'Doctor',
            'status' => 'Step one', // ✅ Must match the enum values exactly
            'email' => '+sharlhany@gmail.com',
            'phone' => '+34674410522',
            'communication_method' => 'Email',
            'last_contact_date' => now(),
            'comment' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
