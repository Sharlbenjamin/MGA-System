<?php

use App\Models\City;
use App\Models\FileFee;
use App\Models\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TIER_SERVICE_TYPE_NAMES = [
        'simple' => ['Simple', 'Simple File Fee'],
        'middle' => ['Middle', 'Middle File Fee', 'Inpatient / Multiple File Fees Cases'],
        'complex' => ['Complex', 'Complex File Fee'],
    ];

    public function up(): void
    {
        Schema::table('file_fees', function (Blueprint $table) {
            $table->string('tier', 16)->nullable()->after('id');
        });

        Schema::create('file_fee_country', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['file_fee_id', 'country_id']);
        });

        Schema::create('file_fee_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['file_fee_id', 'client_id']);
        });

        $this->migrateExistingRows();

        Schema::table('file_fees', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn(['country_id', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::table('file_fees', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('service_type_id')->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });

        $this->restoreLegacyRows();

        Schema::table('file_fees', function (Blueprint $table) {
            $table->dropColumn('tier');
        });

        Schema::dropIfExists('file_fee_client');
        Schema::dropIfExists('file_fee_country');
    }

    private function migrateExistingRows(): void
    {
        $tierServiceTypeMap = $this->resolveTierServiceTypeMap();

        DB::table('file_fees')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($tierServiceTypeMap) {
                foreach ($rows as $row) {
                    $tier = $tierServiceTypeMap[(int) $row->service_type_id] ?? null;

                    DB::table('file_fees')
                        ->where('id', $row->id)
                        ->update([
                            'tier' => $tier,
                            'service_type_id' => $tier ? null : $row->service_type_id,
                        ]);

                    $countryId = $row->country_id ? (int) $row->country_id : null;

                    if (! $countryId && $row->city_id) {
                        $countryId = City::query()
                            ->whereKey((int) $row->city_id)
                            ->value('country_id');
                    }

                    if ($countryId) {
                        DB::table('file_fee_country')->insert([
                            'file_fee_id' => $row->id,
                            'country_id' => $countryId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

  /**
     * @return array<int, string>
     */
    private function resolveTierServiceTypeMap(): array
    {
        $map = [];

        foreach (self::TIER_SERVICE_TYPE_NAMES as $tier => $names) {
            foreach ($names as $name) {
                $serviceTypeId = ServiceType::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
                    ->value('id');

                if ($serviceTypeId) {
                    $map[(int) $serviceTypeId] = $tier;
                }
            }
        }

        return $map;
    }

    private function restoreLegacyRows(): void
    {
        $tierServiceTypeIds = $this->resolveTierServiceTypeIds();

        FileFee::query()
            ->with(['countries'])
            ->orderBy('id')
            ->chunkById(100, function ($fileFees) use ($tierServiceTypeIds) {
                foreach ($fileFees as $fileFee) {
                    $serviceTypeId = $fileFee->service_type_id;

                    if ($fileFee->tier) {
                        $serviceTypeId = $tierServiceTypeIds[$fileFee->tier] ?? null;
                    }

                    DB::table('file_fees')
                        ->where('id', $fileFee->id)
                        ->update([
                            'service_type_id' => $serviceTypeId,
                            'country_id' => $fileFee->countries->first()?->id,
                            'city_id' => null,
                        ]);
                }
            });
    }

    /**
     * @return array<string, int|null>
     */
    private function resolveTierServiceTypeIds(): array
    {
        $ids = [];

        foreach (self::TIER_SERVICE_TYPE_NAMES as $tier => $names) {
            $ids[$tier] = null;

            foreach ($names as $name) {
                $serviceTypeId = ServiceType::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
                    ->value('id');

                if ($serviceTypeId) {
                    $ids[$tier] = (int) $serviceTypeId;
                    break;
                }
            }
        }

        return $ids;
    }
};
