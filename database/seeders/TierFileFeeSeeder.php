<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\FileFee;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class TierFileFeeSeeder extends Seeder
{
    /**
     * Seed tier file fee service types and UK vs rest pricing rows.
     *
     * Run after ServiceTypesSeeder. Safe to re-run (uses updateOrCreate).
     */
    public function run(): void
    {
        $uk = Country::query()
            ->whereRaw('LOWER(name) IN (?, ?)', ['united kingdom', 'uk'])
            ->first();

        $tiers = [
            'Simple' => ['uk' => 85, 'rest' => 50],
            'Middle' => ['uk' => 200, 'rest' => 150],
            'Complex' => ['uk' => 350, 'rest' => 300],
        ];

        foreach ($tiers as $name => $amounts) {
            $serviceType = ServiceType::firstOrCreate(['name' => $name]);

            if ($uk) {
                FileFee::updateOrCreate(
                    [
                        'service_type_id' => $serviceType->id,
                        'country_id' => $uk->id,
                        'city_id' => null,
                    ],
                    ['amount' => $amounts['uk']],
                );
            }

            FileFee::updateOrCreate(
                [
                    'service_type_id' => $serviceType->id,
                    'country_id' => null,
                    'city_id' => null,
                ],
                ['amount' => $amounts['rest']],
            );
        }
    }
}
