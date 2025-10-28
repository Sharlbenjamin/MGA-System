<?php

namespace App\Filament\Pages;

use App\Models\{Provider, ProviderBranch, ServiceType, City, Country};
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\MultiSelectFilter;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OurNetwork extends Page implements HasTable
{
    protected static ?string $navigationGroup = 'PRM';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $title = 'Our Network';
    protected static string $view = 'filament.pages.blank';

    
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Provider Network Overview')
            ->description('Medical services availability across cities from our active provider network')
            ->columns([
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('branchCities.branch.provider', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->getStateUsing(function ($record) {
                        return $record->country->name ?? 'Unknown';
                    }),
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->sortable(),
                Tables\Columns\TextColumn::make('telemedicine')
                    ->label('Telemedicine')
                    ->html(),
                Tables\Columns\TextColumn::make('house_visit')
                    ->label('House Visit')
                    ->html(),
                Tables\Columns\TextColumn::make('dental')
                    ->label('Dental')
                    ->html(),
                Tables\Columns\TextColumn::make('clinic')
                    ->label('Clinic')
                    ->html(),
            ])
            ->filters([
                MultiSelectFilter::make('country')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        return $query->whereIn('country_id', $data['values']);
                    }),
                MultiSelectFilter::make('city')
                    ->label('City')
                    ->options(City::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        return $query->whereIn('id', $data['values']);
                    }),
                MultiSelectFilter::make('service_types')
                    ->label('Service Types')
                    ->options(ServiceType::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        
                        return $query->whereHas('branchCities.branch.services', function ($q) use ($data) {
                            $q->whereIn('service_type_id', $data['values']);
                        });
                    }),
            ])
            ->groups([
                Group::make('country_id')
                    ->label('Country')
                    ->collapsible(),
            ])
            ->defaultSort('country_id')
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        return City::whereHas('branchCities.branch.provider', function ($query) {
            $query->where('status', 'Active');
        })
        ->with(['country', 'branchCities.branch' => function ($query) {
            $query->whereHas('provider', function ($q) {
                $q->where('status', 'Active');
            })->with(['provider', 'services']);
        }])
        ->leftJoin('countries', 'cities.country_id', '=', 'countries.id')
        ->leftJoin('branch_cities', 'cities.id', '=', 'branch_cities.city_id')
        ->leftJoin('provider_branches', 'branch_cities.provider_branch_id', '=', 'provider_branches.id')
        ->leftJoin('providers', 'provider_branches.provider_id', '=', 'providers.id')
        ->where('providers.status', 'Active')
        ->select('cities.*')
        ->selectRaw('COUNT(DISTINCT providers.id) as provider_count')
        ->groupBy('cities.id', 'cities.name', 'cities.country_id', 'cities.province_id', 'cities.created_at', 'cities.updated_at')
        ->orderBy('provider_count', 'desc')
        ->orderBy('countries.name', 'asc');
    }

}
