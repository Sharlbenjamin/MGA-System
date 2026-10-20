<?php

namespace App\Filament\Pages;

use App\Models\City;
use App\Models\Country;
use App\Models\ServiceType;
use App\Services\CoverageAreaQuery;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class CoverageAreas extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationGroup = 'PRM';

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Coverage Areas';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Coverage Areas';

    protected static ?string $slug = 'coverage-areas';

    protected static string $view = 'filament.pages.coverage-areas';

    #[Url]
    public string $coverage = 'current';

    public function mount(): void
    {
        if (! in_array($this->coverage, ['current', 'missing'], true)) {
            $this->coverage = 'current';
        }
    }

    public function updatedCoverage(): void
    {
        if (! in_array($this->coverage, ['current', 'missing'], true)) {
            $this->coverage = 'current';
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function getSubheading(): ?string
    {
        return 'Active providers and active branches only. A city is covered when a branch lists that city, or when a branch is set to All Country. Telemedicine is shown once, because it is not limited to a city. Missing coverage stays empty until you choose a country and apply filters.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(CoverageAreaQuery::class)->builder(
                $this->coverage,
                $this->appliedFilterId('country_id'),
                $this->appliedFilterId('city_id'),
                $this->appliedFilterId('service_type_id'),
            ))
            ->heading(fn (): string => $this->coverage === 'missing' ? 'Missing coverage' : 'Current coverage')
            ->description(fn (): string => $this->coverage === 'missing'
                ? 'Cities with no active branch for that service. Choose a country first so the full city list is not scanned. Results are paged without a total count.'
                : 'Cities and whole-country branches already in the network. An All Country branch is one row, not one row per city. Results are paged without a total count.')
            ->deferLoading()
            ->deferFilters()
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->columns([
                Tables\Columns\TextColumn::make('country_name')
                    ->label('Country')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $this->sortCoverage($query, 'co.name', 'country_name', $direction);
                    }),
                Tables\Columns\TextColumn::make('city_name')
                    ->label('City')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $this->sortCoverage($query, 'c.name', 'city_name', $direction);
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = '%'.addcslashes($search, '\\%_').'%';

                        if ($this->sortsOnCityTable()) {
                            return $query->where(function (Builder $inner) use ($like) {
                                $inner->where('c.name', 'like', $like)
                                    ->orWhere('co.name', 'like', $like)
                                    ->orWhere('st.name', 'like', $like);
                            });
                        }

                        return $query->where(function (Builder $inner) use ($like) {
                            $inner->where('city_name', 'like', $like)
                                ->orWhere('country_name', 'like', $like)
                                ->orWhere('service_type_name', 'like', $like);
                        });
                    }),
                Tables\Columns\TextColumn::make('service_type_name')
                    ->label('Service type')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $this->sortCoverage($query, 'st.name', 'service_type_name', $direction);
                    }),
                Tables\Columns\TextColumn::make('scope')
                    ->label('Coverage')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'city' => 'City',
                        'country' => 'Entire country',
                        'global' => 'All locations',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'city' => 'success',
                        'country' => 'info',
                        'global' => 'warning',
                        default => 'gray',
                    })
                    ->visible(fn (): bool => $this->coverage === 'current'),
                Tables\Columns\TextColumn::make('branch_count')
                    ->label('Branches')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $query->reorder();

                        return $query->orderBy('branch_count', $direction);
                    })
                    ->visible(fn (): bool => $this->coverage === 'current'),
                Tables\Columns\TextColumn::make('providers')
                    ->label('Providers')
                    ->limit(80)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—')
                    ->visible(fn (): bool => $this->coverage === 'current'),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->query(fn (Builder $query): Builder => $query),
                SelectFilter::make('city_id')
                    ->label('City')
                    ->searchable()
                    ->native(false)
                    ->optionsLimit(50)
                    ->getSearchResultsUsing(function (string $search): array {
                        $search = trim($search);

                        if (mb_strlen($search) < 2) {
                            return [];
                        }

                        return City::query()
                            ->when(
                                $this->draftCountryId(),
                                fn (Builder $query, int $countryId) => $query->where('country_id', $countryId),
                            )
                            ->where('name', 'like', addcslashes($search, '\\%_').'%')
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->getOptionLabelUsing(fn ($value): ?string => City::query()->whereKey($value)->value('name'))
                    ->indicateUsing(function (array $state): array {
                        $value = $state['value'] ?? null;

                        if (blank($value)) {
                            return [];
                        }

                        $name = City::query()->whereKey($value)->value('name');

                        return filled($name) ? [Indicator::make('City: '.$name)] : [];
                    })
                    ->query(fn (Builder $query): Builder => $query),
                SelectFilter::make('service_type_id')
                    ->label('Service type')
                    ->options(fn (): array => ServiceType::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->query(fn (Builder $query): Builder => $query),
            ])
            ->modifyQueryUsing(function (Builder $query): Builder {
                if (filled($this->tableSortColumn)) {
                    return $query;
                }

                if ($this->sortsOnCityTable()) {
                    return $query->orderBy('c.name')->orderBy('st.name');
                }

                return $query
                    ->orderBy('country_name')
                    ->orderBy('city_name')
                    ->orderBy('service_type_name');
            })
            ->emptyStateHeading(fn (): string => $this->emptyHeading())
            ->emptyStateDescription(fn (): string => $this->emptyDescription())
            ->emptyStateIcon('heroicon-o-map')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    protected function paginateTableQuery(Builder $query): Paginator
    {
        $perPage = $this->getTableRecordsPerPage();

        if (! is_numeric($perPage)) {
            $perPage = 25;
        }

        return $query->simplePaginate(
            perPage: (int) $perPage,
            pageName: $this->getTablePaginationPageName(),
        );
    }

    protected function appliedFilterId(string $name): ?int
    {
        $value = data_get($this->getTableFilterState($name), 'value');

        return filled($value) ? (int) $value : null;
    }

    protected function draftCountryId(): ?int
    {
        $value = data_get($this->tableDeferredFilters, 'country_id.value')
            ?? data_get($this->tableFilters, 'country_id.value');

        return filled($value) ? (int) $value : null;
    }

    protected function sortsOnCityTable(): bool
    {
        return $this->coverage === 'missing' && filled($this->appliedFilterId('country_id'));
    }

    protected function sortCoverage(Builder $query, string $cityColumn, string $alias, string $direction): Builder
    {
        $query->reorder();

        if ($this->sortsOnCityTable()) {
            return $query->orderBy($cityColumn, $direction);
        }

        return $query->orderBy($alias, $direction);
    }

    protected function emptyHeading(): string
    {
        if ($this->coverage === 'missing' && $this->telemedicineFilterSelected()) {
            return 'Telemedicine is not a city gap';
        }

        if ($this->coverage === 'missing' && ! $this->appliedFilterId('country_id')) {
            return 'Select a country';
        }

        return $this->coverage === 'missing' ? 'No missing coverage' : 'No current coverage';
    }

    protected function emptyDescription(): string
    {
        if ($this->coverage === 'missing' && $this->telemedicineFilterSelected()) {
            return 'Open Current coverage and filter to Telemedicine to see who offers it.';
        }

        if ($this->coverage === 'missing' && ! $this->appliedFilterId('country_id')) {
            return 'Pick a country, then apply the filters. The gap list is loaded for that country only.';
        }

        if ($this->coverage === 'missing') {
            return 'Every city in this filter is already covered, or an All Country branch covers the service.';
        }

        return 'No active branch matches these filters.';
    }

    protected function telemedicineFilterSelected(): bool
    {
        $serviceTypeId = $this->appliedFilterId('service_type_id');

        if (! $serviceTypeId) {
            return false;
        }

        return in_array($serviceTypeId, app(CoverageAreaQuery::class)->telemedicineIds(), true);
    }
}
