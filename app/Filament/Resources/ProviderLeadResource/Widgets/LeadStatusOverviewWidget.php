<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Filament\Resources\ProviderLeadResource;
use App\Models\ProviderLead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class LeadStatusOverviewWidget extends BaseWidget
{
    protected static string $view = 'filament.widgets.client-leads-overview';

    protected ?string $heading = 'Lead status';

    protected ?string $description = 'Counts are for Potential providers. Click a status to filter the list.';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $stats = [];

        foreach ([
            'Pending information',
            'Step one',
            'Step one sent',
            'Reminder',
            'Reminder sent',
            'Discount',
            'Discount sent',
            'Step two',
            'Step two sent',
            'Presentation',
            'Presentation sent',
            'Contract',
            'Contract sent',
            'Fake Case',
            'Fake Case sent',
            'Cancel Case',
            'Cancel Case sent',
            'Error',
        ] as $status) {
            $stats[] = $this->statusStat($status);
        }

        return $stats;
    }

    protected function statusStat(string $status): Stat
    {
        return Stat::make($status, $this->leadsForStatus($status)->count())
            ->description('Potential providers')
            ->descriptionIcon('heroicon-m-tag')
            ->color($this->colorFor($status))
            ->url(ProviderLeadResource::getUrl('index', [
                'tableFilters' => [
                    'status' => [
                        'values' => [$status],
                    ],
                ],
            ]));
    }

    protected function leadsForStatus(string $status): Builder
    {
        return ProviderLead::query()
            ->where('provider_leads.status', $status)
            ->whereHas('provider', fn (Builder $providerQuery) => $providerQuery->where('status', 'Potential'));
    }

    protected function colorFor(string $status): string
    {
        return match ($status) {
            'Error', 'Cancel Case', 'Cancel Case sent' => 'danger',
            'Contract', 'Contract sent' => 'success',
            'Pending information' => 'gray',
            'Reminder', 'Reminder sent', 'Discount', 'Discount sent' => 'warning',
            default => 'info',
        };
    }
}
