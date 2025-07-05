<?php

namespace App\Filament\Resources\PriceListResource\Pages;

use App\Filament\Resources\PriceListResource;
use App\Models\Country;
use App\Models\PriceList;
use App\Models\ServiceType;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

class ListPriceLists extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PriceListResource::class;

    protected static string $view = 'filament.resources.price-list-resource.pages.list-price-lists';

    public function getTitle(): string
    {
        return 'Price Lists by Country';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('city.name')
                    ->label('City')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('providerBranch.branch_name')
                    ->label('Provider Branch')
                    ->sortable()
                    ->searchable()
                    ->placeholder('General pricing'),

                TextColumn::make('day_price')
                    ->label('Day Price')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('weekend_price')
                    ->label('Weekend Price')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('night_weekday_price')
                    ->label('Night Weekday')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('night_weekend_price')
                    ->label('Night Weekend')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                BadgeColumn::make('suggested_markup')
                    ->label('Markup')
                    ->formatStateUsing(fn ($state) => $state ? (($state - 1) * 100) . '%' : 'N/A')
                    ->colors(['success', 'warning'])
                    ->alignCenter(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(function () {
                        // Clear cache after deletion
                        PriceListResource::clearCache();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function () {
                            // Clear cache after bulk deletion
                            PriceListResource::clearCache();
                        }),
                ]),
            ])
            ->defaultSort('city_id');
    }

    protected function getTableQuery(): Builder
    {
        return PriceList::query()
            ->with(['country', 'city', 'serviceType', 'providerBranch.provider']);
    }

    public function getCountriesWithPriceLists()
    {
        return Cache::remember('countries_with_price_lists', 300, function () {
            return Country::whereHas('priceLists')
                ->withCount('priceLists')
                ->with(['priceLists' => function ($query) {
                    $query->with(['city', 'serviceType', 'providerBranch.provider']);
                }])
                ->get();
        });
    }

    public function getServiceTypes()
    {
        return Cache::remember('service_types', 300, function () {
            return ServiceType::all();
        });
    }

    public function getPriceListsForCountryAndServiceType($countryId, $serviceTypeId)
    {
        $cacheKey = "price_lists_country_{$countryId}_service_{$serviceTypeId}";
        
        return Cache::remember($cacheKey, 300, function () use ($countryId, $serviceTypeId) {
            return PriceList::query()
                ->where('country_id', $countryId)
                ->where('service_type_id', $serviceTypeId)
                ->with(['country', 'city', 'serviceType', 'providerBranch.provider'])
                ->get();
        });
    }
}
