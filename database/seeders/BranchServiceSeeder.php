<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BranchService;
use App\Models\ProviderBranch;
use App\Models\ServiceType;

class BranchServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $serviceTypes = ServiceType::all();
        $providerBranches = ProviderBranch::all();

        foreach ($providerBranches as $branch) {
            // Create branch services for each service type
            foreach ($serviceTypes as $serviceType) {
                BranchService::create([
                    'provider_branch_id' => $branch->id,
                    'service_type_id' => $serviceType->id,
                    'day_cost' => rand(50, 200),
                    'night_cost' => rand(75, 250),
                    'weekend_cost' => rand(60, 220),
                    'weekend_night_cost' => rand(85, 280),
                    'is_active' => true,
                ]);
            }
        }
    }
}
