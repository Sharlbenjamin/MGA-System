<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Models\ProviderLead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PotentialProviderLeadsByCountryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('UK + Ireland (New)', $this->countNewLeadsThisMonth(['GB', 'IE']))
                ->description('Created this month')
                ->color('info'),
            Stat::make('France (New)', $this->countNewLeadsThisMonth(['FR']))
                ->description('Created this month')
                ->color('info'),
            Stat::make('Italy (New)', $this->countNewLeadsThisMonth(['IT']))
                ->description('Created this month')
                ->color('info'),
            Stat::make('Spain (New)', $this->countNewLeadsThisMonth(['ES']))
                ->description('Created this month')
                ->color('info'),

            Stat::make('UK + Ireland (Active)', $this->countActiveLeadsThisMonth(['GB', 'IE']))
                ->description('Last contact this month')
                ->color('success'),
            Stat::make('France (Active)', $this->countActiveLeadsThisMonth(['FR']))
                ->description('Last contact this month')
                ->color('success'),
            Stat::make('Italy (Active)', $this->countActiveLeadsThisMonth(['IT']))
                ->description('Last contact this month')
                ->color('success'),
            Stat::make('Spain (Active)', $this->countActiveLeadsThisMonth(['ES']))
                ->description('Last contact this month')
                ->color('success'),
        ];
    }

    protected function baseQuery(array $countryIsoCodes): Builder
    {
        return ProviderLead::query()
            ->join('providers', 'providers.id', '=', 'provider_leads.provider_id')
            ->join('countries', 'countries.id', '=', 'providers.country_id')
            ->where('providers.status', 'Potential')
            ->whereIn('countries.iso', $countryIsoCodes);
    }

    protected function countNewLeadsThisMonth(array $countryIsoCodes): int
    {
        return (clone $this->baseQuery($countryIsoCodes))
            ->whereMonth('provider_leads.created_at', now()->month)
            ->whereYear('provider_leads.created_at', now()->year)
            ->count('provider_leads.id');
    }

    protected function countActiveLeadsThisMonth(array $countryIsoCodes): int
    {
        return (clone $this->baseQuery($countryIsoCodes))
            ->whereNotNull('provider_leads.last_contact_date')
            ->whereMonth('provider_leads.last_contact_date', now()->month)
            ->whereYear('provider_leads.last_contact_date', now()->year)
            ->count('provider_leads.id');
    }
}
