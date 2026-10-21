<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Filament\Resources\ProviderLeadResource;
use App\Models\Country;
use App\Models\Interaction;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class MonthlyInteractionsByCountryWidget extends BaseWidget
{
    protected static string $view = 'filament.widgets.client-leads-overview';

    protected ?string $heading = 'Interactions this month';

    protected ?string $description = 'Counts from the interactions table for the current calendar month. France is listed first.';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $france = ['FR', 'France'];
        $ukIreland = ['GB', 'UK', 'IE', 'United Kingdom', 'Great Britain', 'Ireland'];
        $italy = ['IT', 'Italy'];
        $spain = ['ES', 'Spain'];

        return [
            $this->countryStat('France', $france, 'primary', 'heroicon-m-flag'),
            $this->countryStat('UK & Ireland', $ukIreland, 'info', 'heroicon-m-globe-alt'),
            $this->countryStat('Italy', $italy, 'warning', 'heroicon-m-globe-alt'),
            $this->countryStat('Spain', $spain, 'success', 'heroicon-m-globe-alt'),
            Stat::make('All countries', $this->countInteractionsThisMonth())
                ->description('Provider lead interactions')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('gray')
                ->url(ProviderLeadResource::getUrl('index', [
                    'tableFilters' => [
                        'contacted_this_month' => ['isActive' => true],
                    ],
                ])),
        ];
    }

    /**
     * @param  list<string>  $countryKeys
     */
    protected function countryStat(string $label, array $countryKeys, string $color, string $icon): Stat
    {
        $count = $this->countInteractionsThisMonth($countryKeys);
        $countryIds = $this->countryIdsForKeys($countryKeys);

        $filters = [
            'contacted_this_month' => ['isActive' => true],
        ];

        if ($countryIds !== []) {
            $filters['country_id'] = ['values' => $countryIds];
        }

        return Stat::make($label, $count)
            ->description('Interactions this month')
            ->descriptionIcon($icon)
            ->color($color)
            ->url(ProviderLeadResource::getUrl('index', [
                'tableFilters' => $filters,
            ]));
    }

    /**
     * @param  list<string>|null  $countryKeys
     */
    protected function countInteractionsThisMonth(?array $countryKeys = null): int
    {
        [$startOfMonth, $endOfMonth] = $this->currentMonthRange();

        $query = Interaction::query()
            ->whereNotNull('interactions.provider_lead_id')
            ->whereDate('interactions.interaction_date', '>=', $startOfMonth->toDateString())
            ->whereDate('interactions.interaction_date', '<=', $endOfMonth->toDateString());

        if ($countryKeys !== null) {
            $query->whereHas('providerLead.provider.country', function (Builder $countryQuery) use ($countryKeys): void {
                $countryQuery
                    ->whereIn('iso', $countryKeys)
                    ->orWhereIn('name', $countryKeys)
                    ->orWhereIn('nicename', $countryKeys);
            });
        }

        return $query->count();
    }

    /**
     * @param  list<string>  $countryKeys
     * @return list<int|string>
     */
    protected function countryIdsForKeys(array $countryKeys): array
    {
        return Country::query()
            ->where(function (Builder $query) use ($countryKeys): void {
                $query
                    ->whereIn('iso', $countryKeys)
                    ->orWhereIn('name', $countryKeys)
                    ->orWhereIn('nicename', $countryKeys);
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function currentMonthRange(): array
    {
        return [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ];
    }
}
