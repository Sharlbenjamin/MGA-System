<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\FileFee;
use Illuminate\Database\Seeder;

class TierFileFeeSeeder extends Seeder
{
    /**
     * Seed tier file fee rows and UK vs rest pricing.
     *
     * Safe to re-run (uses updateOrCreate).
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
<<<<<<< HEAD
            FileFee::TIER_SIMPLE => ['uk' => 85, 'rest' => 50],
            FileFee::TIER_MIDDLE => ['uk' => 200, 'rest' => 150],
            FileFee::TIER_COMPLEX => ['uk' => 350, 'rest' => 300],
=======
            'Simple' => ['uk' => 85, 'greece' => 70, 'rest' => 50],
            'Middle' => ['uk' => 200, 'greece' => 180, 'rest' => 150],
            'Complex' => ['uk' => 350, 'greece' => 320, 'rest' => 300],
>>>>>>> staging
        ];

        foreach ($tiers as $tier => $amounts) {
            $this->seedRestFee($tier, $amounts['rest']);

            if ($uk) {
                $this->seedCountryFee($tier, $uk->id, $amounts['uk']);
            }
<<<<<<< HEAD
=======

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
>>>>>>> staging
        }
    }

    private function seedRestFee(string $tier, float $amount): void
    {
        $fee = FileFee::query()
            ->where('tier', $tier)
            ->whereNull('service_type_id')
            ->whereDoesntHave('countries')
            ->whereDoesntHave('clients')
            ->first();

        if ($fee) {
            $fee->update(['amount' => $amount]);

            return;
        }

        FileFee::create([
            'tier' => $tier,
            'service_type_id' => null,
            'amount' => $amount,
        ]);
    }

    private function seedCountryFee(string $tier, int $countryId, float $amount): void
    {
        $fee = FileFee::query()
            ->where('tier', $tier)
            ->whereNull('service_type_id')
            ->whereHas('countries', fn ($query) => $query->whereKey($countryId))
            ->whereDoesntHave('clients')
            ->first();

        if ($fee) {
            $fee->update(['amount' => $amount]);
            $fee->countries()->sync([$countryId]);

            return;
        }

        $fee = FileFee::create([
            'tier' => $tier,
            'service_type_id' => null,
            'amount' => $amount,
        ]);

        $fee->countries()->sync([$countryId]);
    }
}
