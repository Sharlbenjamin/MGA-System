<?php

namespace App\Services;

use App\Models\CoverageArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A city is covered for a service when an active branch of an active provider
 * in that country lists the city, or is marked all-country, and offers the service.
 * Telemedicine is not tied to a city.
 */
class CoverageAreaQuery
{
    /** @var array<int>|null */
    private ?array $telemedicineIds = null;

    public function builder(string $coverage, ?int $countryId, ?int $cityId, ?int $serviceTypeId): Builder
    {
        if ($coverage === 'missing') {
            return $this->missingBuilder($countryId, $cityId, $serviceTypeId);
        }

        return CoverageArea::query()
            ->fromSub($this->current($countryId, $cityId, $serviceTypeId), 'coverage_areas')
            ->select('coverage_areas.*');
    }

    /**
     * @return array<int>
     */
    public function telemedicineIds(): array
    {
        if ($this->telemedicineIds !== null) {
            return $this->telemedicineIds;
        }

        $this->telemedicineIds = DB::table('service_types')
            ->where(function (QueryBuilder $query) {
                $query->where('id', 2)
                    ->orWhereRaw('LOWER(name) = ?', ['telemedicine']);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->telemedicineIds;
    }

    private function missingBuilder(?int $countryId, ?int $cityId, ?int $serviceTypeId): Builder
    {
        $requestedCountryId = $countryId;
        $requestedCityId = $cityId;
        [$countryId, $cityId, $invalid] = $this->locationFilters($countryId, $cityId);

        if (! $requestedCountryId && ! $requestedCityId) {
            return $this->emptyBuilder();
        }

        $query = CoverageArea::query()
            ->from('cities as c')
            ->join('countries as co', 'co.id', '=', 'c.country_id');

        if ($invalid || ! $countryId) {
            return $this->selectMissingColumns(
                $query->join('service_types as st', fn ($join) => $join->whereRaw('1 = 0'))
                    ->whereRaw('1 = 0')
            );
        }

        $serviceIds = $this->serviceIdsWithoutCountrywideCoverage($countryId, $serviceTypeId);

        $query->where('c.country_id', $countryId)
            ->when($cityId, fn (Builder $builder) => $builder->where('c.id', $cityId));

        if ($serviceIds === []) {
            $query->join('service_types as st', fn ($join) => $join->whereRaw('1 = 0'))
                ->whereRaw('1 = 0');
        } else {
            $query->join('service_types as st', fn ($join) => $join->whereIn('st.id', $serviceIds))
                ->whereNotExists(function (QueryBuilder $gap) {
                    $gap->selectRaw('1')
                        ->from('branch_cities as bc')
                        ->join('provider_branches as pb', function ($join) {
                            $join->on('pb.id', '=', 'bc.provider_branch_id')
                                ->where('pb.status', 'Active');
                        })
                        ->join('providers as p', function ($join) {
                            $join->on('p.id', '=', 'pb.provider_id')
                                ->where('p.status', 'Active')
                                ->whereColumn('p.country_id', 'c.country_id');
                        })
                        ->join('branch_service as bs', function ($join) {
                            $join->on('bs.provider_branch_id', '=', 'pb.id')
                                ->whereColumn('bs.service_type_id', 'st.id');
                        })
                        ->whereColumn('bc.city_id', 'c.id');
                });
        }

        return $this->selectMissingColumns($query);
    }

    private function selectMissingColumns(Builder $query): Builder
    {
        return $query
            ->selectRaw($this->compositeId('missing', 'c.id', 'st.id').' as id')
            ->selectRaw("'city' as scope")
            ->selectRaw('co.id as country_id')
            ->selectRaw('co.name as country_name')
            ->selectRaw('c.id as city_id')
            ->selectRaw('c.name as city_name')
            ->selectRaw('st.id as service_type_id')
            ->selectRaw('st.name as service_type_name')
            ->selectRaw('0 as branch_count')
            ->selectRaw('NULL as providers');
    }

    private function current(?int $countryId, ?int $cityId, ?int $serviceTypeId): QueryBuilder
    {
        [$countryId, $cityId, $invalid] = $this->locationFilters($countryId, $cityId);

        if ($invalid) {
            return $this->none();
        }

        $parts = [];

        if ($this->includesLocationServices($serviceTypeId)) {
            $parts[] = $this->cityCoverage($countryId, $cityId, $serviceTypeId);
            $parts[] = $this->countryCoverage($countryId, $serviceTypeId);
        }

        if ($this->includesTelemedicine($serviceTypeId)) {
            $parts[] = $this->telemedicineCoverage($serviceTypeId);
        }

        if ($parts === []) {
            return $this->none();
        }

        /** @var QueryBuilder $query */
        $query = array_shift($parts);

        foreach ($parts as $part) {
            $query->unionAll($part);
        }

        return $query;
    }

    private function cityCoverage(?int $countryId, ?int $cityId, ?int $serviceTypeId): QueryBuilder
    {
        $query = $this->activeBranchQuery()
            ->where('pb.all_country', false)
            ->join('branch_cities as bc', 'bc.provider_branch_id', '=', 'pb.id')
            ->join('cities as c', function ($join) {
                $join->on('c.id', '=', 'bc.city_id')
                    ->whereColumn('c.country_id', 'p.country_id');
            })
            ->join('countries as co', 'co.id', '=', 'c.country_id')
            ->when($countryId, fn (QueryBuilder $builder) => $builder->where('co.id', $countryId))
            ->when($cityId, fn (QueryBuilder $builder) => $builder->where('c.id', $cityId))
            ->when($serviceTypeId, fn (QueryBuilder $builder) => $builder->where('st.id', $serviceTypeId));

        $this->excludeTelemedicine($query);

        return $query
            ->groupBy('c.id', 'c.name', 'co.id', 'co.name', 'st.id', 'st.name')
            ->selectRaw($this->compositeId('city', 'c.id', 'st.id').' as id')
            ->selectRaw("'city' as scope")
            ->selectRaw('co.id as country_id')
            ->selectRaw('co.name as country_name')
            ->selectRaw('c.id as city_id')
            ->selectRaw('c.name as city_name')
            ->selectRaw('st.id as service_type_id')
            ->selectRaw('st.name as service_type_name')
            ->selectRaw('COUNT(DISTINCT pb.id) as branch_count')
            ->selectRaw($this->providersExpression().' as providers');
    }

    private function countryCoverage(?int $countryId, ?int $serviceTypeId): QueryBuilder
    {
        $query = $this->activeBranchQuery()
            ->join('countries as co', 'co.id', '=', 'p.country_id')
            ->where('pb.all_country', true)
            ->when($countryId, fn (QueryBuilder $builder) => $builder->where('co.id', $countryId))
            ->when($serviceTypeId, fn (QueryBuilder $builder) => $builder->where('st.id', $serviceTypeId));

        $this->excludeTelemedicine($query);

        return $query
            ->groupBy('co.id', 'co.name', 'st.id', 'st.name')
            ->selectRaw($this->compositeId('country', 'co.id', 'st.id').' as id')
            ->selectRaw("'country' as scope")
            ->selectRaw('co.id as country_id')
            ->selectRaw('co.name as country_name')
            ->selectRaw('NULL as city_id')
            ->selectRaw("'Entire country' as city_name")
            ->selectRaw('st.id as service_type_id')
            ->selectRaw('st.name as service_type_name')
            ->selectRaw('COUNT(DISTINCT pb.id) as branch_count')
            ->selectRaw($this->providersExpression().' as providers');
    }

    private function telemedicineCoverage(?int $serviceTypeId): QueryBuilder
    {
        $ids = $this->telemedicineIds();

        $query = $this->activeBranchQuery()
            ->whereIn('st.id', $ids)
            ->when($serviceTypeId, fn (QueryBuilder $builder) => $builder->where('st.id', $serviceTypeId));

        return $query
            ->groupBy('st.id', 'st.name')
            ->selectRaw($this->compositeId('global', '0', 'st.id').' as id')
            ->selectRaw("'global' as scope")
            ->selectRaw('0 as country_id')
            ->selectRaw("'All countries' as country_name")
            ->selectRaw('NULL as city_id')
            ->selectRaw("'All locations' as city_name")
            ->selectRaw('st.id as service_type_id')
            ->selectRaw('st.name as service_type_name')
            ->selectRaw('COUNT(DISTINCT pb.id) as branch_count')
            ->selectRaw($this->providersExpression().' as providers');
    }

    private function activeBranchQuery(): QueryBuilder
    {
        return DB::table('provider_branches as pb')
            ->join('providers as p', function ($join) {
                $join->on('p.id', '=', 'pb.provider_id')
                    ->where('p.status', 'Active');
            })
            ->join('branch_service as bs', 'bs.provider_branch_id', '=', 'pb.id')
            ->join('service_types as st', 'st.id', '=', 'bs.service_type_id')
            ->where('pb.status', 'Active');
    }

    private function excludeTelemedicine(QueryBuilder $query): void
    {
        $ids = $this->telemedicineIds();

        if ($ids !== []) {
            $query->whereNotIn('st.id', $ids);
        }
    }

    /**
     * @return array<int>
     */
    private function serviceIdsWithoutCountrywideCoverage(int $countryId, ?int $serviceTypeId): array
    {
        $query = DB::table('service_types as st')
            ->when($serviceTypeId, fn (QueryBuilder $builder) => $builder->where('st.id', $serviceTypeId))
            ->whereNotExists(function (QueryBuilder $covered) use ($countryId) {
                $covered->selectRaw('1')
                    ->from('provider_branches as pb')
                    ->join('providers as p', function ($join) use ($countryId) {
                        $join->on('p.id', '=', 'pb.provider_id')
                            ->where('p.status', 'Active')
                            ->where('p.country_id', $countryId);
                    })
                    ->join('branch_service as bs', function ($join) {
                        $join->on('bs.provider_branch_id', '=', 'pb.id')
                            ->whereColumn('bs.service_type_id', 'st.id');
                    })
                    ->where('pb.status', 'Active')
                    ->where('pb.all_country', true);
            });

        $this->excludeTelemedicine($query);

        return $query->pluck('st.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function includesLocationServices(?int $serviceTypeId): bool
    {
        if (! $serviceTypeId) {
            return true;
        }

        return ! in_array($serviceTypeId, $this->telemedicineIds(), true);
    }

    private function includesTelemedicine(?int $serviceTypeId): bool
    {
        $ids = $this->telemedicineIds();

        if ($ids === []) {
            return false;
        }

        if (! $serviceTypeId) {
            return true;
        }

        return in_array($serviceTypeId, $ids, true);
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: bool}
     */
    private function locationFilters(?int $countryId, ?int $cityId): array
    {
        if (! $cityId) {
            return [$countryId, null, false];
        }

        $cityCountryId = DB::table('cities')->where('id', $cityId)->value('country_id');

        if (! $cityCountryId || ($countryId && (int) $cityCountryId !== $countryId)) {
            return [null, null, true];
        }

        return [(int) $cityCountryId, $cityId, false];
    }

    private function emptyBuilder(): Builder
    {
        return CoverageArea::query()
            ->fromSub($this->none(), 'coverage_areas')
            ->select('coverage_areas.*');
    }

    private function none(): QueryBuilder
    {
        return DB::query()
            ->selectRaw("'' as id")
            ->selectRaw("'' as scope")
            ->selectRaw('0 as country_id')
            ->selectRaw("'' as country_name")
            ->selectRaw('NULL as city_id')
            ->selectRaw("'' as city_name")
            ->selectRaw('0 as service_type_id')
            ->selectRaw("'' as service_type_name")
            ->selectRaw('0 as branch_count')
            ->selectRaw('NULL as providers')
            ->whereRaw('1 = 0');
    }

    private function compositeId(string $scope, string $leftSql, string $rightSql): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "'{$scope}-' || ({$leftSql}) || '-' || ({$rightSql})";
        }

        return "CONCAT('{$scope}-', {$leftSql}, '-', {$rightSql})";
    }

    private function providersExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'GROUP_CONCAT(DISTINCT p.name)';
        }

        return "GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ' · ')";
    }
}
