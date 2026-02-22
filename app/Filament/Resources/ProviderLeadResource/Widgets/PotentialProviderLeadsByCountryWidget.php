<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Models\ProviderLead;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PotentialProviderLeadsByCountryWidget extends BaseWidget
{
    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $ukIreland = ['GB', 'UK', 'IE', 'United Kingdom', 'Great Britain', 'Ireland'];
        $france = ['FR', 'France'];
        $italy = ['IT', 'Italy'];
        $spain = ['ES', 'Spain'];

        return [
            Stat::make('New - UK & Ireland', $this->countNewLeadsThisMonth($ukIreland))
                ->description('Created this month')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary'),
            Stat::make('New - France', $this->countNewLeadsThisMonth($france))
                ->description('Created this month')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),
            Stat::make('New - Italy', $this->countNewLeadsThisMonth($italy))
                ->description('Created this month')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),
            Stat::make('New - Spain', $this->countNewLeadsThisMonth($spain))
                ->description('Created this month')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success'),

            Stat::make('Active - UK & Ireland', $this->countActiveLeadsThisMonth($ukIreland))
                ->description('Created + contacted this month')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('primary'),
            Stat::make('Active - France', $this->countActiveLeadsThisMonth($france))
                ->description('Created + contacted this month')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),
            Stat::make('Active - Italy', $this->countActiveLeadsThisMonth($italy))
                ->description('Created + contacted this month')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('warning'),
            Stat::make('Active - Spain', $this->countActiveLeadsThisMonth($spain))
                ->description('Created + contacted this month')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),
        ];
    }

    protected function baseQuery(array $countryKeys, string $providerStatus = 'Active'): Builder
    {
        return ProviderLead::query()
            ->whereHas('provider', function (Builder $query) use ($countryKeys, $providerStatus) {
                $query
                    ->where('status', $providerStatus)
                    ->whereHas('country', function (Builder $countryQuery) use ($countryKeys) {
                        $countryQuery
                            ->whereIn('iso', $countryKeys)
                            ->orWhereIn('name', $countryKeys)
                            ->orWhereIn('nicename', $countryKeys);
                    });
            });
    }

    protected function countNewLeadsThisMonth(array $countryKeys): int
    {
        [$startOfMonth, $endOfMonth] = $this->currentMonthRange();

        return (clone $this->baseQuery($countryKeys, 'Potential'))
            ->whereDate('provider_leads.created_at', '>=', $startOfMonth->toDateString())
            ->whereDate('provider_leads.created_at', '<=', $endOfMonth->toDateString())
            ->count();
    }

    protected function countActiveLeadsThisMonth(array $countryKeys): int
    {
        [$startOfMonth, $endOfMonth] = $this->currentMonthRange();

        return (clone $this->baseQuery($countryKeys))
            ->whereBetween('provider_leads.created_at', [$startOfMonth, $endOfMonth])
            ->whereNotNull('provider_leads.last_contact_date')
            ->whereBetween('provider_leads.last_contact_date', [$startOfMonth, $endOfMonth])
            ->count();
    }

    protected function currentMonthRange(): array
    {
        return [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ];
    }
}
