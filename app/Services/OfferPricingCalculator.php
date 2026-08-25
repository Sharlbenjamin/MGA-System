<?php

namespace App\Services;

use App\Models\Country;
use App\Models\File;
use App\Models\FileFee;
use App\Models\GopItem;
use App\Models\PriceList;
use App\Models\ProviderBranch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class OfferPricingCalculator
{
    public const SERVICE_HOUSE_VISIT = 'house_visit';

    public const SERVICE_TELEMEDICINE = 'telemedicine';

    public const SERVICE_OTHER = 'other';

    public const TIER_SIMPLE = 'simple';

    public const TIER_MIDDLE = 'middle';

    public const TIER_COMPLEX = 'complex';

    public const COUNTRY_UK = 'uk';

    public const COUNTRY_GREECE = 'greece';

    public const COUNTRY_DEFAULT = 'default';

    /**
     * @return self::SERVICE_HOUSE_VISIT|self::SERVICE_TELEMEDICINE|self::SERVICE_OTHER
     */
    public function classifyService(?int $serviceTypeId = null, ?string $serviceTypeName = null): string
    {
        $normalized = mb_strtolower(trim((string) $serviceTypeName));

        if ($normalized !== '') {
            if ($this->nameMatches($normalized, config('offer.house_visit.service_type_names', ['House Call', 'House Visit']))) {
                return self::SERVICE_HOUSE_VISIT;
            }

            if ($this->nameMatches($normalized, config('offer.telemedicine.service_type_names', ['Telemedicine']))) {
                return self::SERVICE_TELEMEDICINE;
            }
        }

        if ($serviceTypeId === 1) {
            return self::SERVICE_HOUSE_VISIT;
        }

        if ($serviceTypeId === 2) {
            return self::SERVICE_TELEMEDICINE;
        }

        return self::SERVICE_OTHER;
    }

    public function isHouseVisit(?int $serviceTypeId = null, ?string $serviceTypeName = null): bool
    {
        return $this->classifyService($serviceTypeId, $serviceTypeName) === self::SERVICE_HOUSE_VISIT;
    }

    public function isTelemedicine(?int $serviceTypeId = null, ?string $serviceTypeName = null): bool
    {
        return $this->classifyService($serviceTypeId, $serviceTypeName) === self::SERVICE_TELEMEDICINE;
    }

    public function resolveCountryGroup(?File $file = null, ?Country $country = null): string
    {
        $country ??= $file?->relationLoaded('country') ? $file->country : $file?->country;

        $name = mb_strtolower(trim((string) ($country?->nicename ?: $country?->name ?: '')));
        $iso = strtoupper(trim((string) ($country?->iso ?? '')));
        $iso3 = strtoupper(trim((string) ($country?->iso3 ?? '')));

        if ($this->matchesCountryGroup($name, $iso, $iso3, self::COUNTRY_UK)) {
            return self::COUNTRY_UK;
        }

        if ($this->matchesCountryGroup($name, $iso, $iso3, self::COUNTRY_GREECE)) {
            return self::COUNTRY_GREECE;
        }

        if ($file?->country_id && class_exists(TaxExportHelpers::class) && TaxExportHelpers::isUkCountryId((int) $file->country_id)) {
            return self::COUNTRY_UK;
        }

        return self::COUNTRY_DEFAULT;
    }

    /**
     * @return self::TIER_SIMPLE|self::TIER_MIDDLE|self::TIER_COMPLEX|null
     */
    public function determineTier(float $itemsCostWithoutFees): ?string
    {
        if ($itemsCostWithoutFees <= 0) {
            return null;
        }

        $middleFrom = (float) config('offer.file_fee_thresholds.middle', 350);
        $complexFrom = (float) config('offer.file_fee_thresholds.complex', 1000);

        if ($itemsCostWithoutFees > $complexFrom) {
            return self::TIER_COMPLEX;
        }

        if ($itemsCostWithoutFees > $middleFrom) {
            return self::TIER_MIDDLE;
        }

        return self::TIER_SIMPLE;
    }

    public function resolveConfiguredFileFeeAmount(string $countryGroup, string $tier): float
    {
        $amounts = config("offer.file_fee_amounts.{$countryGroup}", config('offer.file_fee_amounts.default', []));

        return round((float) ($amounts[$tier] ?? 0), 2);
    }

    public function resolveFileFeeAmount(
        File $file,
        float $itemsCostWithoutFees,
        ?int $serviceTypeId = null,
        ?string $serviceTypeName = null,
    ): float {
        $serviceTypeId ??= $file->service_type_id ? (int) $file->service_type_id : null;
        $serviceTypeName ??= $file->serviceType?->name;

        if ($this->isHouseVisit($serviceTypeId, $serviceTypeName) || $this->isTelemedicine($serviceTypeId, $serviceTypeName)) {
            return 0.0;
        }

        $tier = $this->determineTier($itemsCostWithoutFees);

        if ($tier === null) {
            return 0.0;
        }

        $fromTable = $this->lookupFileFeeFromTables($file, $tier);

        if ($fromTable !== null) {
            return $fromTable;
        }

        return $this->resolveConfiguredFileFeeAmount(
            $this->resolveCountryGroup($file),
            $tier,
        );
    }

    /**
     * Selling cost is system-calculated. Privileged users may override after this suggestion.
     */
    public function calculateSellingCost(
        float $cost,
        File $file,
        ?float $systemSellingCost = null,
        ?int $serviceTypeId = null,
        ?string $serviceTypeName = null,
    ): float {
        $serviceTypeId ??= $file->service_type_id ? (int) $file->service_type_id : null;
        $serviceTypeName ??= $file->serviceType?->name;
        $kind = $this->classifyService($serviceTypeId, $serviceTypeName);
        $multiplier = (float) config('offer.selling_cost_multiplier', 2);

        if ($kind === self::SERVICE_HOUSE_VISIT) {
            if ($systemSellingCost !== null && $systemSellingCost > 0) {
                return round($systemSellingCost, 2);
            }

            return round($cost * $multiplier, 2);
        }

        if ($kind === self::SERVICE_TELEMEDICINE) {
            $fixed = $this->telemedicineSellingPrice($file);

            if ($cost > ($fixed / $multiplier)) {
                return round($cost * $multiplier, 2);
            }

            return round($fixed, 2);
        }

        if ($systemSellingCost !== null && $systemSellingCost > 0) {
            return round($systemSellingCost, 2);
        }

        return round($cost * $multiplier, 2);
    }

    public function telemedicineSellingPrice(File $file): float
    {
        $group = $this->resolveCountryGroup($file);

        if ($group === self::COUNTRY_UK) {
            return round((float) config('offer.telemedicine.uk', 85), 2);
        }

        return round((float) config('offer.telemedicine.default', 75), 2);
    }

    /**
     * @return array{cost: float, selling_cost: float, description: string}|null
     */
    public function resolveProviderCost(File $file, ProviderBranch $branch, ?int $serviceTypeId = null): ?array
    {
        $serviceTypeId ??= $file->service_type_id ? (int) $file->service_type_id : null;

        if (! $serviceTypeId) {
            return null;
        }

        $service = $branch->relationLoaded('services')
            ? $branch->services->firstWhere('id', $serviceTypeId)
            : $branch->services()->where('service_types.id', $serviceTypeId)->first();

        $cost = $service?->pivot?->min_cost !== null ? (float) $service->pivot->min_cost : null;
        $systemSelling = $service?->pivot?->max_cost !== null ? (float) $service->pivot->max_cost : null;
        $description = $service?->name ?: ($file->serviceType?->name ?: 'Service');

        if ($cost === null || $cost <= 0) {
            $fromPriceList = $this->lookupCostFromPriceList($file, $branch, $serviceTypeId);

            if ($fromPriceList !== null) {
                $cost = $fromPriceList['cost'];
                $systemSelling = $systemSelling ?: $fromPriceList['selling_cost'];
                $description = $fromPriceList['description'] ?: $description;
            }
        }

        if ($cost === null || $cost <= 0) {
            return null;
        }

        return [
            'cost' => round($cost, 2),
            'selling_cost' => $this->calculateSellingCost(
                $cost,
                $file,
                $systemSelling,
                $serviceTypeId,
                $description,
            ),
            'description' => $description,
        ];
    }

    /**
     * @return list<array{description: string, cost: float, selling_cost: float, item_type: string, sort_order: int}>
     */
    public function buildSuggestedItems(File $file, ProviderBranch $branch, ?int $serviceTypeId = null): array
    {
        $resolved = $this->resolveProviderCost($file, $branch, $serviceTypeId);
        $serviceTypeId ??= $file->service_type_id ? (int) $file->service_type_id : null;
        $serviceTypeName = $resolved['description'] ?? $file->serviceType?->name;

        $items = [];

        if ($resolved) {
            $items[] = [
                'description' => $resolved['description'],
                'cost' => $resolved['cost'],
                'selling_cost' => $resolved['selling_cost'],
                'item_type' => GopItem::TYPE_SERVICE,
                'sort_order' => 0,
            ];
        } elseif ($this->isTelemedicine($serviceTypeId, $serviceTypeName)) {
            $items[] = [
                'description' => $serviceTypeName ?: 'Telemedicine',
                'cost' => 0.0,
                'selling_cost' => $this->telemedicineSellingPrice($file),
                'item_type' => GopItem::TYPE_SERVICE,
                'sort_order' => 0,
            ];
        }

        return $this->withFileFeeItem($items, $file, $serviceTypeId, $serviceTypeName);
    }

    /**
     * @param  list<array{description?: string, cost?: float|int|string|null, selling_cost?: float|int|string|null, item_type?: string, sort_order?: int}>  $items
     * @return list<array{description: string, cost: float, selling_cost: float, item_type: string, sort_order: int}>
     */
    public function withFileFeeItem(
        array $items,
        File $file,
        ?int $serviceTypeId = null,
        ?string $serviceTypeName = null,
    ): array {
        $serviceItems = [];

        foreach (array_values($items) as $index => $item) {
            $type = $item['item_type'] ?? GopItem::TYPE_SERVICE;

            if ($type === GopItem::TYPE_FILE_FEE) {
                continue;
            }

            $cost = round((float) ($item['cost'] ?? 0), 2);
            $selling = isset($item['selling_cost']) && $item['selling_cost'] !== null && $item['selling_cost'] !== ''
                ? round((float) $item['selling_cost'], 2)
                : $this->calculateSellingCost($cost, $file, null, $serviceTypeId, $serviceTypeName);

            $serviceItems[] = [
                'description' => trim((string) ($item['description'] ?? '')) ?: 'Item',
                'cost' => $cost,
                'selling_cost' => $selling,
                'item_type' => GopItem::TYPE_SERVICE,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ];
        }

        $itemsCost = $this->sumItemCosts($serviceItems);
        $fileFee = $this->resolveFileFeeAmount($file, $itemsCost, $serviceTypeId, $serviceTypeName);

        if ($fileFee > 0) {
            $serviceItems[] = [
                'description' => 'File fee',
                'cost' => 0.0,
                'selling_cost' => $fileFee,
                'item_type' => GopItem::TYPE_FILE_FEE,
                'sort_order' => count($serviceItems),
            ];
        }

        return $serviceItems;
    }

    /**
     * @param  iterable<int, array<string, mixed>|GopItem>  $items
     */
    public function sumItemCosts(iterable $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $type = $item instanceof GopItem
                ? $item->item_type
                : ($item['item_type'] ?? GopItem::TYPE_SERVICE);

            if ($type === GopItem::TYPE_FILE_FEE) {
                continue;
            }

            $cost = $item instanceof GopItem
                ? (float) $item->cost
                : (float) ($item['cost'] ?? 0);

            $total += $cost;
        }

        return round($total, 2);
    }

    /**
     * @param  iterable<int, array<string, mixed>|GopItem>  $items
     * @return array{cost: float, offered_cost: float, file_fee: float, total: float}
     */
    public function totals(iterable $items): array
    {
        $cost = 0.0;
        $offered = 0.0;
        $fileFee = 0.0;

        foreach ($items as $item) {
            $type = $item instanceof GopItem
                ? $item->item_type
                : ($item['item_type'] ?? GopItem::TYPE_SERVICE);
            $itemCost = $item instanceof GopItem
                ? (float) $item->cost
                : (float) ($item['cost'] ?? 0);
            $selling = $item instanceof GopItem
                ? (float) $item->selling_cost
                : (float) ($item['selling_cost'] ?? 0);

            if ($type === GopItem::TYPE_FILE_FEE) {
                $fileFee += $selling > 0 ? $selling : $itemCost;

                continue;
            }

            $cost += $itemCost;
            $offered += $selling;
        }

        $cost = round($cost, 2);
        $offered = round($offered, 2);
        $fileFee = round($fileFee, 2);

        return [
            'cost' => $cost,
            'offered_cost' => $offered,
            'file_fee' => $fileFee,
            'total' => round($offered + $fileFee, 2),
        ];
    }

    public static function userCanEditSellingCost(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole([
            'admin',
            'Admin',
            'super-admin',
            'Super Admin',
            'super admin',
        ])) {
            return true;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                return $user->hasPermissionTo('edit Gop selling cost');
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return array{cost: float, selling_cost: ?float, description: string}|null
     */
    protected function lookupCostFromPriceList(File $file, ProviderBranch $branch, int $serviceTypeId): ?array
    {
        try {
            if (! Schema::hasTable('price_lists')) {
                return null;
            }

            $query = PriceList::query()
                ->where('service_type_id', $serviceTypeId)
                ->where(function ($inner) use ($branch) {
                    $inner->where('provider_branch_id', $branch->id)
                        ->orWhereNull('provider_branch_id');
                });

            if ($file->country_id) {
                $query->where(function ($inner) use ($file) {
                    $inner->where('country_id', $file->country_id)
                        ->orWhereNull('country_id');
                });
            }

            if ($file->city_id) {
                $query->where(function ($inner) use ($file) {
                    $inner->where('city_id', $file->city_id)
                        ->orWhereNull('city_id');
                });
            }

            $row = $query
                ->orderByRaw('provider_branch_id IS NULL')
                ->orderByRaw('city_id IS NULL')
                ->orderByRaw('country_id IS NULL')
                ->first();

            if (! $row) {
                return null;
            }

            $price = (float) ($row->day_price ?? 0);

            if ($price <= 0) {
                return null;
            }

            return [
                'cost' => round($price, 2),
                'selling_cost' => null,
                'description' => $file->serviceType?->name ?: 'Service',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    protected function lookupFileFeeFromTables(File $file, string $tier): ?float
    {
        try {
            if (! $file->exists || ! Schema::hasTable('file_fees')) {
                return null;
            }

            $serviceType = app(InvoiceFileFeeService::class)->findServiceTypeForTier($tier);

            if (! $serviceType) {
                return null;
            }

            $countryId = $file->country_id ? (int) $file->country_id : null;
            $cityId = $file->city_id ? (int) $file->city_id : null;

            $candidates = Collection::make();

            if ($countryId && $cityId) {
                $candidates->push(
                    FileFee::query()
                        ->where('service_type_id', $serviceType->id)
                        ->where('country_id', $countryId)
                        ->where('city_id', $cityId)
                        ->first()
                );
            }

            if ($countryId) {
                $candidates->push(
                    FileFee::query()
                        ->where('service_type_id', $serviceType->id)
                        ->where('country_id', $countryId)
                        ->whereNull('city_id')
                        ->first()
                );
            }

            $candidates->push(
                FileFee::query()
                    ->where('service_type_id', $serviceType->id)
                    ->whereNull('country_id')
                    ->whereNull('city_id')
                    ->first()
            );

            $match = $candidates->first(fn ($row) => $row && $row->amount !== null);

            return $match ? round((float) $match->amount, 2) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $names
     */
    protected function nameMatches(string $normalized, array $names): bool
    {
        foreach ($names as $name) {
            $candidate = mb_strtolower(trim((string) $name));

            if ($candidate !== '' && (str_contains($normalized, $candidate) || str_contains($candidate, $normalized))) {
                return true;
            }
        }

        return false;
    }

    protected function matchesCountryGroup(string $name, string $iso, string $iso3, string $group): bool
    {
        foreach (config("offer.country_names.{$group}", []) as $candidate) {
            if ($name !== '' && $name === mb_strtolower(trim((string) $candidate))) {
                return true;
            }
        }

        $codes = array_map('strtoupper', config("offer.country_iso.{$group}", []));

        return ($iso !== '' && in_array($iso, $codes, true))
            || ($iso3 !== '' && in_array($iso3, $codes, true));
    }
}
