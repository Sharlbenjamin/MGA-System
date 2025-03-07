<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::firstOrCreate([
            'company_name' => 'Coris Brazil',
            'type' => 'Agency',
            'status' => 'Active',
            'initials' => 'CB',
            'number_requests' => 0,
        ]);
    }
}
