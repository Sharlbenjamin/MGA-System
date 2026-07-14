<?php

use App\Models\FileFee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_fees', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->decimal('simple_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('middle_amount', 10, 2)->nullable()->after('simple_amount');
            $table->decimal('complex_amount', 10, 2)->nullable()->after('middle_amount');
            $table->decimal('simple_max_total', 10, 2)->nullable()->after('complex_amount');
            $table->decimal('middle_max_total', 10, 2)->nullable()->after('simple_max_total');
        });

        Schema::table('file_fees', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->change();
        });

        Schema::create('file_fee_city', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['file_fee_id', 'city_id']);
        });

        $this->migrateTierRowsToPackages();
    }

    public function down(): void
    {
        $this->restoreTierRowsFromPackages();

        Schema::dropIfExists('file_fee_city');

        Schema::table('file_fees', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'simple_amount',
                'middle_amount',
                'complex_amount',
                'simple_max_total',
                'middle_max_total',
            ]);
        });

        Schema::table('file_fees', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable(false)->change();
        });
    }

    private function migrateTierRowsToPackages(): void
    {
        $defaultSimpleMax = (float) config('invoice.file_fee_tiers.simple.max_total', 350);
        $defaultMiddleMax = (float) config('invoice.file_fee_tiers.middle.max_total', 1000);

        $tierRows = DB::table('file_fees')
            ->whereNotNull('tier')
            ->orderBy('id')
            ->get();

        if ($tierRows->isEmpty()) {
            return;
        }

        $groups = [];

        foreach ($tierRows as $row) {
            $signature = $this->buildScopeSignature((int) $row->id);
            $groups[$signature][] = $row;
        }

        foreach ($groups as $rows) {
            $amounts = [
                FileFee::TIER_SIMPLE => null,
                FileFee::TIER_MIDDLE => null,
                FileFee::TIER_COMPLEX => null,
            ];

            foreach ($rows as $row) {
                $tier = (string) $row->tier;
                if (array_key_exists($tier, $amounts)) {
                    $amounts[$tier] = (float) $row->amount;
                }
            }

            $keepRow = $rows[0];
            $keepId = (int) $keepRow->id;

            DB::table('file_fees')
                ->where('id', $keepId)
                ->update([
                    'tier' => null,
                    'service_type_id' => null,
                    'amount' => null,
                    'simple_amount' => $amounts[FileFee::TIER_SIMPLE],
                    'middle_amount' => $amounts[FileFee::TIER_MIDDLE],
                    'complex_amount' => $amounts[FileFee::TIER_COMPLEX],
                    'simple_max_total' => $defaultSimpleMax,
                    'middle_max_total' => $defaultMiddleMax,
                    'updated_at' => now(),
                ]);

            $deleteIds = collect($rows)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $keepId)
                ->values()
                ->all();

            if ($deleteIds !== []) {
                DB::table('file_fees')->whereIn('id', $deleteIds)->delete();
            }
        }
    }

    private function restoreTierRowsFromPackages(): void
    {
        $packages = DB::table('file_fees')
            ->whereNull('service_type_id')
            ->where(function ($query) {
                $query->whereNotNull('simple_amount')
                    ->orWhereNotNull('middle_amount')
                    ->orWhereNotNull('complex_amount');
            })
            ->orderBy('id')
            ->get();

        foreach ($packages as $package) {
            $countryIds = DB::table('file_fee_country')
                ->where('file_fee_id', $package->id)
                ->pluck('country_id')
                ->all();

            $clientIds = DB::table('file_fee_client')
                ->where('file_fee_id', $package->id)
                ->pluck('client_id')
                ->all();

            $cityIds = DB::table('file_fee_city')
                ->where('file_fee_id', $package->id)
                ->pluck('city_id')
                ->all();

            $tiers = [
                FileFee::TIER_SIMPLE => $package->simple_amount,
                FileFee::TIER_MIDDLE => $package->middle_amount,
                FileFee::TIER_COMPLEX => $package->complex_amount,
            ];

            $firstTierId = null;

            foreach ($tiers as $tier => $amount) {
                if ($amount === null) {
                    continue;
                }

                $newId = DB::table('file_fees')->insertGetId([
                    'tier' => $tier,
                    'service_type_id' => null,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $firstTierId ??= $newId;

                foreach ($countryIds as $countryId) {
                    DB::table('file_fee_country')->insert([
                        'file_fee_id' => $newId,
                        'country_id' => $countryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($clientIds as $clientId) {
                    DB::table('file_fee_client')->insert([
                        'file_fee_id' => $newId,
                        'client_id' => $clientId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($firstTierId !== null) {
                DB::table('file_fees')->where('id', $package->id)->delete();
            }
        }
    }

    private function buildScopeSignature(int $fileFeeId): string
    {
        $countryIds = DB::table('file_fee_country')
            ->where('file_fee_id', $fileFeeId)
            ->pluck('country_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $cityIds = DB::table('file_fee_city')
            ->where('file_fee_id', $fileFeeId)
            ->pluck('city_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $clientIds = DB::table('file_fee_client')
            ->where('file_fee_id', $fileFeeId)
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        return implode('|', [
            implode(',', $countryIds),
            implode(',', $cityIds),
            implode(',', $clientIds),
        ]);
    }
};
