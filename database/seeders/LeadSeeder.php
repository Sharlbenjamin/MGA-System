<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Lead::firstOrCreate([
            'client_id' => 1,
            'email' => 'sharlhany@gmail.com',
            'first_name' => 'Sharl',
            'status' => 'Introduction',
        ]);;
    }
}
