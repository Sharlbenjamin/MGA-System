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

        $greece = Country::query()
            ->whereRaw('LOWER(name) IN (?, ?)', ['greece', 'hellas'])
            ->first();

        $tiers = [
            'Simple' => ['uk' => 85, 'greece' => 70, 'rest' => 50],
            'Middle' => ['uk' => 200, 'greece' => 180, 'rest' => 150],
            'Complex' => ['uk' => 350, 'greece' => 320, 'rest' => 300],
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

            if ($greece) {
                FileFee::updateOrCreate(
                    [
                        'service_type_id' => $serviceType->id,
                        'country_id' => $greece->id,
                        'city_id' => null,
                    ],
                    ['amount' => $amounts['greece']],
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
