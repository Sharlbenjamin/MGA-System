<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Models\ProviderLead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PotentialProviderLeadsByCountryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $leadsByCountry = ProviderLead::query()
            ->selectRaw("COALESCE(countries.name, 'No Country') as country_name, COUNT(provider_leads.id) as leads_count")
            ->leftJoin('providers', 'providers.id', '=', 'provider_leads.provider_id')
            ->leftJoin('countries', 'countries.id', '=', 'providers.country_id')
            ->where('providers.status', 'Potential')
            ->groupBy('countries.id', 'countries.name')
            ->orderByDesc('leads_count')
            ->get();

        return $leadsByCountry
            ->map(fn ($item) => Stat::make($item->country_name, (int) $item->leads_count)
                ->description('New provider leads')
                ->color('info'))
            ->values()
            ->all();
    }
}
