<?php

namespace App\Services;

use App\Models\FileFee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FileFeeResolver
{
    /**
     * Resolve a tier-based file fee amount for invoice pricing.
     */
    public function resolveTierAmount(
        string $tier,
        ?int $countryId = null,
        ?int $clientId = null,
    ): ?float {
        return $this->resolveAmount(
            fn (Builder $query) => $query->where('tier', $tier),
            $countryId,
            $clientId,
        );
    }

    /**
     * Resolve a service-type-based file fee amount.
     */
    public function resolveServiceTypeAmount(
        int $serviceTypeId,
        ?int $countryId = null,
        ?int $clientId = null,
    ): ?float {
        return $this->resolveAmount(
            fn (Builder $query) => $query
                ->where('service_type_id', $serviceTypeId)
                ->whereNull('tier'),
            $countryId,
            $clientId,
        );
    }

    /**
     * @return Collection<int, FileFee>
     */
    public function matchingServiceTypeFees(
        int $serviceTypeId,
        ?int $countryId = null,
        ?int $clientId = null,
    ): Collection {
        return $this->matchingFees(
            fn (Builder $query) => $query
                ->where('service_type_id', $serviceTypeId)
                ->whereNull('tier'),
            $countryId,
            $clientId,
        );
    }

    private function resolveAmount(
        callable $scope,
        ?int $countryId,
        ?int $clientId,
    ): ?float {
        $match = $this->matchingFees($scope, $countryId, $clientId)->first();

        return $match ? (float) $match->amount : null;
    }

    /**
     * @return Collection<int, FileFee>
     */
    private function matchingFees(
        callable $scope,
        ?int $countryId,
        ?int $clientId,
    ): Collection {
        $query = FileFee::query()
            ->with(['countries'])
            ->withCount(['countries', 'clients']);

        $scope($query);

        $candidates = $query->get();

        return $candidates
            ->filter(fn (FileFee $fee) => $this->matchesCountryScope($fee, $countryId))
            ->filter(fn (FileFee $fee) => $this->matchesClientScope($fee, $clientId))
            ->sortByDesc(fn (FileFee $fee) => $this->specificityScore($fee, $countryId, $clientId))
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

    private function specificityScore(FileFee $fee, ?int $countryId, ?int $clientId): int
    {
        $score = 0;

        if ($fee->clients_count > 0 && $clientId && $this->feeAppliesToClient($fee, $clientId)) {
            $score += 100;
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
