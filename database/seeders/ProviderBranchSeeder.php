<?php

namespace Database\Seeders;

use App\Models\ProviderBranch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProviderBranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProviderBranch::create([
            'provider_id' => 1,
            'branch_name' => 'Dr. Helen House Call',
            'city_id' => null,
            'status' => 'Active',
            'priority' => 1,
        ]);
    }
}
