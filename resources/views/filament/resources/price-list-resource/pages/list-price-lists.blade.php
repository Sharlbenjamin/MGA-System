<x-filament-panels::page>
    @php
        $countriesWithPriceLists = $this->getCountriesWithPriceLists();
    @endphp

    @if($countriesWithPriceLists->count() === 0)
        <x-filament::section>
            <div class="text-center py-12">
                <div class="mx-auto h-12 w-12 text-gray-400">
                    <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                </div>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No price lists found</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating your first price list.</p>
                <div class="mt-6">
                    <x-filament::button
                        color="primary"
                        tag="a"
                        href="{{ route('filament.admin.resources.price-lists.create') }}"
                    >
                        Create Price List
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @else
        <div x-data="{ activeTab: '{{ $countriesWithPriceLists->first()->id ?? '' }}' }">
            <x-filament::tabs>
                @foreach($countriesWithPriceLists as $country)
                    <x-filament::tabs.item 
                        :active="$loop->first"
                        :badge="$country->price_lists_count"
                        @click="activeTab = '{{ $country->id }}'"
                        :class="$loop->first ? 'active' : ''"
                    >
                        {{ $country->name }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <div class="mt-6">
                @foreach($countriesWithPriceLists as $country)
                    <div x-show="activeTab === '{{ $country->id }}'" x-cloak>
                        @php
                            $allPriceLists = collect();
                            foreach($this->getServiceTypes() as $serviceType) {
                                $priceLists = $this->getPriceListsForCountryAndServiceType($country->id, $serviceType->id);
                                foreach($priceLists as $priceList) {
                                    $allPriceLists->push($priceList);
                                }
                            }
                        @endphp

                        @if($allPriceLists->count() > 0)
                            <x-filament::section>
                                <x-slot name="heading">
                                    {{ $country->name }} - All Price Lists
                                    <span class="text-sm text-gray-500">({{ $allPriceLists->count() }} entries)</span>
                                </x-slot>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left text-gray-500">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-4 py-3">
                                                    Service Type
                                                </th>
                                                <th scope="col" class="px-4 py-3">
                                                    City
                                                </th>
                                                <th scope="col" class="px-4 py-3 text-right">
                                                    Day Price
                                                </th>
                                                <th scope="col" class="px-4 py-3 text-right">
                                                    Weekend Price
                                                </th>
                                                <th scope="col" class="px-4 py-3 text-right">
                                                    Night Weekday
                                                </th>
                                                <th scope="col" class="px-4 py-3 text-right">
                                                    Night Weekend
                                                </th>
                                                <th scope="col" class="px-4 py-3 text-right">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($allPriceLists as $priceList)
                                                <tr class="bg-white border-b hover:bg-gray-50">
                                                    <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                                                        {{ $priceList->serviceType->name ?? 'N/A' }}
                                                    </td>
                                                    <td class="px-4 py-3 text-gray-500">
                                                        {{ $priceList->city->name ?? 'N/A' }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        @if($priceList->day_price)
                                                            €{{ number_format($priceList->day_price, 2) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        @if($priceList->weekend_price)
                                                            €{{ number_format($priceList->weekend_price, 2) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        @if($priceList->night_weekday_price)
                                                            €{{ number_format($priceList->night_weekday_price, 2) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        @if($priceList->night_weekend_price)
                                                            €{{ number_format($priceList->night_weekend_price, 2) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <div class="flex justify-end">
                                                            <x-filament::button
                                                                size="sm"
                                                                color="primary"
                                                                tag="a"
                                                                href="{{ route('filament.admin.resources.price-lists.edit', $priceList) }}"
                                                            >
                                                                Edit
                                                            </x-filament::button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </x-filament::section>
                        @else
                            <x-filament::section>
                                <div class="text-center py-8">
                                    <div class="mx-auto h-8 w-8 text-gray-400">
                                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                        </svg>
                                    </div>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No price lists for {{ $country->name }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">Create price lists for this country to see them here.</p>
                                </div>
                            </x-filament::section>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page> 