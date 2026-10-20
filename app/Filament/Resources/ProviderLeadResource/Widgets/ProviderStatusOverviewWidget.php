<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Filament\Resources\ProviderLeadResource;
use App\Models\Provider;
use App\Models\ProviderLead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ProviderStatusOverviewWidget extends BaseWidget
{
    protected static string $view = 'filament.widgets.client-leads-overview';

    protected ?string $heading = 'Provider status';

    protected ?string $description = 'Click a status to filter the leads. Potential stays selected until you choose another status.';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        return [
            $this->statusStat('Potential', 'info', 'heroicon-m-sparkles', true),
            $this->statusStat('Searching', 'gray', 'heroicon-m-magnifying-glass', false),
            $this->statusStat('Active', 'success', 'heroicon-m-check-badge', false),
            $this->statusStat('Hold', 'warning', 'heroicon-m-pause', false),
            $this->statusStat('Black List', 'danger', 'heroicon-m-no-symbol', false),
        ];
    }

    protected function statusStat(string $status, string $color, string $icon, bool $hideErrorLeads): Stat
    {
        $leads = $this->leadsForProviderStatus($status, $hideErrorLeads);
        $providers = Provider::query()->where('status', $status)->count();

        return Stat::make($status, (clone $leads)->count())
            ->description($providers.' '.($providers === 1 ? 'provider' : 'providers'))
            ->descriptionIcon($icon)
            ->color($color)
            ->url(ProviderLeadResource::getUrl('index', [
                'tableFilters' => [
                    'provider_status' => [
                        'values' => [$status],
                    ],
                ],
            ]));
    }

    protected function leadsForProviderStatus(string $status, bool $hideErrorLeads): Builder
    {
        $query = ProviderLead::query()
            ->whereHas('provider', fn (Builder $providerQuery) => $providerQuery->where('status', $status));

        if ($hideErrorLeads) {
            $query->whereRaw('LOWER(provider_leads.status) != ?', ['error']);
        }

        return $query;
    }
}
