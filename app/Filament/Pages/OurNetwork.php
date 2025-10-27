<?php

namespace App\Filament\Pages;

use App\Models\{Provider, ProviderBranch, ServiceType, City, Country};
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\MultiSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OurNetwork extends Page
{
    protected static ?string $navigationGroup = 'PRM';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $title = 'Our Network';
    protected static string $view = 'filament-panels::pages.list-records';
    
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Provider Network Overview')
            ->description('Medical services availability across cities from our active provider network')
            ->columns([
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->sortable()
                    ->searchable()
                    ->groupable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('telemedicine')
                    ->label('Telemedicine')
                    ->formatStateUsing(function ($record) {
                        return $this->formatServiceAvailability($record, 'Telemedicine');
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('house_visit')
                    ->label('House Visit')
                    ->formatStateUsing(function ($record) {
                        return $this->formatServiceAvailability($record, 'House Call');
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('dental')
                    ->label('Dental')
                    ->formatStateUsing(function ($record) {
                        return $this->formatServiceAvailability($record, 'Dental Clinic');
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('clinic')
                    ->label('Clinic')
                    ->formatStateUsing(function ($record) {
                        return $this->formatServiceAvailability($record, 'Clinic Visit');
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('cost')
                    ->label('Cost')
                    ->formatStateUsing(function ($record) {
                        return $this->formatCost($record);
                    })
                    ->html(),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return $query->where('country_id', $data['value']);
                    }),
                SelectFilter::make('city')
                    ->label('City')
                    ->options(City::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return $query->where('id', $data['value']);
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
            ->defaultGroup('country')
            ->defaultSort('country')
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        // Get all cities that have active provider branches
        $cities = City::whereHas('branchCities.branch.provider', function ($query) {
            $query->where('status', 'Active');
        })->with(['country', 'branchCities.branch' => function ($query) {
            $query->whereHas('provider', function ($q) {
                $q->where('status', 'Active');
            })->with(['provider', 'services']);
        }])->get();

        // Transform the data for the table
        $tableData = collect();
        
        foreach ($cities as $city) {
            $tableData->push([
                'id' => $city->id,
                'country' => $city->country->name ?? 'Unknown',
                'city' => $city->name,
                'city_id' => $city->id,
                'services' => $this->getCityServices($city),
            ]);
        }

        // Convert to a query builder-like structure
        return new class($tableData) extends Builder {
            private $data;
            
            public function __construct($data) {
                $this->data = $data;
            }
            
            public function get() {
                return $this->data;
            }
            
            public function paginate($perPage = 15) {
                return $this->data;
            }
        };
    }

    protected function getCityServices($city)
    {
        $services = collect();
        
        foreach ($city->branchCities as $branchCity) {
            $branch = $branchCity->branch;
            if ($branch && $branch->provider && $branch->provider->status === 'Active') {
                foreach ($branch->services as $service) {
                    $services->push([
                        'service_name' => $service->name,
                        'min_cost' => $service->pivot->min_cost,
                        'max_cost' => $service->pivot->max_cost,
                        'provider_name' => $branch->provider->name,
                    ]);
                }
            }
        }
        
        return $services;
    }

    protected function formatServiceAvailability($record, $serviceName)
    {
        $hasService = $record['services']->contains('service_name', $serviceName);
        
        if ($hasService) {
            $service = $record['services']->firstWhere('service_name', $serviceName);
            $cost = $this->formatCostRange($service['min_cost'], $service['max_cost']);
            return "<span class='text-green-600 font-medium'>Available</span><br><small class='text-gray-500'>{$cost}</small>";
        }
        
        return "<span class='text-red-600 font-medium'>Missing</span>";
    }

    protected function formatCost($record)
    {
        $services = $record['services'];
        
        if ($services->isEmpty()) {
            return "<span class='text-red-600 font-medium'>No Services</span>";
        }
        
        $costs = $services->map(function ($service) {
            return $this->formatCostRange($service['min_cost'], $service['max_cost']);
        })->filter()->unique();
        
        return $costs->implode('<br>');
    }

    protected function formatCostRange($minCost, $maxCost)
    {
        if (!$minCost && !$maxCost) {
            return 'Price on request';
        }
        
        if ($minCost && $maxCost) {
            if ($minCost == $maxCost) {
                return "€{$minCost}";
            }
            return "€{$minCost} - €{$maxCost}";
        }
        
        if ($minCost) {
            return "From €{$minCost}";
        }
        
        if ($maxCost) {
            return "Up to €{$maxCost}";
        }
        
        return 'Price on request';
    }
}
