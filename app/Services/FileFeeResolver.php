<?php

namespace App\Services;

use App\Models\FileFee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FileFeeResolver
{
    /**
     * Resolve the best matching tier package for invoicing caps and amounts.
     */
    public function resolveTierPackage(
        ?int $countryId = null,
        ?int $cityId = null,
        ?int $clientId = null,
    ): ?FileFee {
        return $this->matchingFees(
            fn (Builder $query) => $query
                ->whereNull('service_type_id')
                ->where(function (Builder $query) {
                    $query->whereNotNull('simple_amount')
                        ->orWhereNotNull('middle_amount')
                        ->orWhereNotNull('complex_amount');
                }),
            $countryId,
            $cityId,
            $clientId,
        )->first();
    }

    /**
     * Resolve a tier-based file fee amount for invoice pricing.
     */
    public function resolveTierAmount(
        string $tier,
        ?int $countryId = null,
        ?int $cityId = null,
        ?int $clientId = null,
    ): ?float {
        $package = $this->resolveTierPackage($countryId, $cityId, $clientId);

        if ($package) {
            return $package->amountForTier($tier);
        }

        return $this->resolveAmount(
            fn (Builder $query) => $query->where('tier', $tier),
            $countryId,
            $cityId,
            $clientId,
        );
    }

    /**
     * Resolve a service-type-based file fee amount.
     */
    public function resolveServiceTypeAmount(
        int $serviceTypeId,
        ?int $countryId = null,
        ?int $cityId = null,
        ?int $clientId = null,
    ): ?float {
        return $this->resolveAmount(
            fn (Builder $query) => $query
                ->where('service_type_id', $serviceTypeId)
                ->whereNull('tier'),
            $countryId,
            $cityId,
            $clientId,
        );
    }

    /**
     * @return Collection<int, FileFee>
     */
    public function matchingServiceTypeFees(
        int $serviceTypeId,
        ?int $countryId = null,
        ?int $cityId = null,
        ?int $clientId = null,
    ): Collection {
        return $this->matchingFees(
            fn (Builder $query) => $query
                ->where('service_type_id', $serviceTypeId)
                ->whereNull('tier'),
            $countryId,
            $cityId,
            $clientId,
        );
    }

    private function resolveAmount(
        callable $scope,
        ?int $countryId,
        ?int $cityId,
        ?int $clientId,
    ): ?float {
        $match = $this->matchingFees($scope, $countryId, $cityId, $clientId)->first();

        return $match && $match->amount !== null ? (float) $match->amount : null;
    }

    /**
     * @return Collection<int, FileFee>
     */
    private function matchingFees(
        callable $scope,
        ?int $countryId,
        ?int $cityId,
        ?int $clientId,
    ): Collection {
        $query = FileFee::query()
            ->with(['countries', 'cities', 'clients'])
            ->withCount(['countries', 'cities', 'clients']);

        $scope($query);

        $candidates = $query->get();

        return $candidates
            ->filter(fn (FileFee $fee) => $this->matchesCountryScope($fee, $countryId))
            ->filter(fn (FileFee $fee) => $this->matchesCityScope($fee, $cityId))
            ->filter(fn (FileFee $fee) => $this->matchesClientScope($fee, $clientId))
            ->sortByDesc(fn (FileFee $fee) => $this->specificityScore($fee, $countryId, $cityId, $clientId))
            ->values();
    }

    private function matchesCountryScope(FileFee $fee, ?int $countryId): bool
    {
        if ($fee->countries_count === 0) {
            return true;
        }

        if (! $countryId) {
            return false;
        }

        return $fee->countries->contains('id', $countryId);
    }

    private function matchesCityScope(FileFee $fee, ?int $cityId): bool
    {
        if ($fee->cities_count === 0) {
            return true;
        }

        if (! $cityId) {
            return false;
        }

        return $fee->cities->contains('id', $cityId);
    }

    private function matchesClientScope(FileFee $fee, ?int $clientId): bool
    {
        if ($fee->clients_count === 0) {
            return true;
        }

        if (! $clientId) {
            return false;
        }

        return $this->feeAppliesToClient($fee, $clientId);
    }

    private function specificityScore(
        FileFee $fee,
        ?int $countryId,
        ?int $cityId,
        ?int $clientId,
    ): int {
        $score = 0;

        if ($fee->clients_count > 0 && $clientId && $this->feeAppliesToClient($fee, $clientId)) {
            $score += 100;
        }

        if ($fee->cities_count > 0 && $cityId && $fee->cities->contains('id', $cityId)) {
            $score += 50;
        }

        if ($fee->countries_count > 0 && $countryId && $fee->countries->contains('id', $countryId)) {
            $score += 10;
        }

        return $score;
    }

    private function feeAppliesToClient(FileFee $fee, int $clientId): bool
    {
        return DB::table('file_fee_client')
            ->where('file_fee_id', $fee->id)
            ->where('client_id', $clientId)
            ->exists();
    }
}
