<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use App\Models\Provider;
use App\Models\ServiceType;
use App\Filament\Widgets\ProviderNetworkStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\MultiSelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OurNetworkProvider extends ListRecords
{
    protected static string $resource = ProviderResource::class;

    protected static ?string $title = 'Our Network';

    protected static ?string $navigationLabel = 'Our Network';

    protected static ?string $slug = 'our-network';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTabs(): array
{
    return [
        'all' => Tab::make('All Providers')
            ->badge(Provider::count()),
        'active' => Tab::make('Active')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Active'))
            ->badge(Provider::where('status', 'Active')->count()),
        'doctors' => Tab::make('Doctors')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'Doctor'))
            ->badge(Provider::where('type', 'Doctor')->count()),
    ];
}

    protected function getHeaderWidgets(): array
    {
        return [
            ProviderNetworkStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Provider::query()
                    ->with(['country', 'branches.services'])
                    ->withCount(['branches'])
            )
            ->columns([
                TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('branches.city.name')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $cities = $record->branches->pluck('city.name')->filter()->unique()->take(3);
                        return $cities->isNotEmpty() ? $cities->implode(', ') : 'N/A';
                    }),

                BadgeColumn::make('telemedicine')
                    ->label('Telemedicine')
                    ->formatStateUsing(function ($record) {
                        $hasTelemedicine = $record->branches->where('telemedicine', true)->count() > 0;
                        return $hasTelemedicine ? 'Yes' : 'No';
                    })
                    ->colors([
                        'Yes' => 'success',
                        'No' => 'gray',
                    ]),

                BadgeColumn::make('house_calls')
                    ->label('House')
                    ->formatStateUsing(function ($record) {
                        $hasHouseCalls = $record->branches->where('house_calls', true)->count() > 0;
                        return $hasHouseCalls ? 'Yes' : 'No';
                    })
                    ->colors([
                        'Yes' => 'success',
                        'No' => 'gray',
                    ]),

                BadgeColumn::make('dental')
                    ->label('Dental')
                    ->formatStateUsing(function ($record) {
                        $hasDental = $record->branches->where('dental', true)->count() > 0;
                        return $hasDental ? 'Yes' : 'No';
                    })
                    ->colors([
                        'Yes' => 'success',
                        'No' => 'gray',
                    ]),

                BadgeColumn::make('clinic')
                    ->label('Clinic')
                    ->formatStateUsing(function ($record) {
                        $hasClinic = $record->branches->where('clinic', true)->count() > 0;
                        return $hasClinic ? 'Yes' : 'No';
                    })
                    ->colors([
                        'Yes' => 'success',
                        'No' => 'gray',
                    ]),

                TextColumn::make('cost')
                    ->label('Cost')
                    ->formatStateUsing(function ($record) {
                        // Only show cost to managers and financial department
                        $user = Auth::user();
                        $canViewCost = $user && (
                            $user->roles->contains('name', 'manager') || 
                            $user->roles->contains('name', 'financial')
                        );
                        
                        if (!$canViewCost) {
                            return '***';
                        }

                        // Calculate average cost from branch services
                        $totalCost = 0;
                        $serviceCount = 0;
                        
                        foreach ($record->branches as $branch) {
                            foreach ($branch->services as $service) {
                                $totalCost += ($service->pivot->min_cost + $service->pivot->max_cost) / 2;
                                $serviceCount++;
                            }
                        }
                        
                        if ($serviceCount > 0) {
                            $averageCost = $totalCost / $serviceCount;
                            return '€' . number_format($averageCost, 2);
                        }
                        
                        return 'N/A';
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Provider Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Doctor' => 'success',
                        'Hospital' => 'info',
                        'Clinic' => 'warning',
                        'Dental' => 'danger',
                        'Agency' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Hold' => 'warning',
                        'Potential' => 'info',
                        'Black List' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('branches_count')
                    ->label('Branches')
                    ->numeric()
                    ->sortable()
                    ->color('info'),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('city_id')
                    ->label('City')
                    ->relationship('branches.city', 'name')
                    ->searchable()
                    ->preload(),

                MultiSelectFilter::make('service_types')
                    ->label('Service Types')
                    ->options(ServiceType::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['values'],
                            fn (Builder $query, $serviceTypes): Builder => $query->whereHas(
                                'branches.services',
                                fn (Builder $query) => $query->whereIn('service_type_id', $serviceTypes)
                            )
                        );
                    }),

                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Hold' => 'Hold',
                        'Potential' => 'Potential',
                        'Black List' => 'Black List',
                    ]),

                SelectFilter::make('type')
                    ->options([
                        'Doctor' => 'Doctor',
                        'Hospital' => 'Hospital',
                        'Clinic' => 'Clinic',
                        'Dental' => 'Dental',
                        'Agency' => 'Agency',
                    ]),
            ])
            ->groups([
                Tables\Grouping\Group::make('country.name')
                    ->label('Country')
                    ->collapsible(),
            ])
            ->defaultSort('country.name')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Provider $record): string => ProviderResource::getUrl('overview', ['record' => $record])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
