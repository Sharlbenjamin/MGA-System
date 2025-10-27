<?php

namespace App\Filament\Widgets;

use App\Models\Provider;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class ProviderNetworkStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalProviders = Provider::count();
        $activeProviders = Provider::where('status', 'Active')->count();
        $doctorProviders = Provider::where('type', 'Doctor')->count();
        $totalBranches = Provider::withCount('branches')->get()->sum('branches_count');
        $newProvidersThisMonth = Provider::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        return [
            Stat::make('Total Providers', $totalProviders)
                ->description('In our network')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('Active Providers', $activeProviders)
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Doctor Providers', $doctorProviders)
                ->description('Medical doctors')
                ->descriptionIcon('heroicon-m-user')
                ->color('info'),

            Stat::make('Total Branches', $totalBranches)
                ->description('Across all providers')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning'),

            Stat::make('New This Month', $newProvidersThisMonth)
                ->description('Added this month')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('success'),
        ];
    }
}
