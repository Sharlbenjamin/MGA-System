<?php

namespace App\Filament\Resources\LeadResource\Widgets;

use App\Filament\Resources\LeadResource;
use App\Services\LeadOverviewService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientLeadsOverviewWidget extends BaseWidget
{
    protected static string $view = 'filament.widgets.client-leads-overview';

    protected ?string $heading = 'Client leads';

    protected ?string $description = 'Rejected, Black list, Active, and On Hold clients stay hidden until you filter for that status.';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        return array_map(function (array $widget) {
            $stat = Stat::make($widget['label'], $widget['count'])
                ->description($widget['description'])
                ->descriptionIcon($widget['icon'])
                ->color($widget['color']);

            $tableFilters = $this->tableFiltersFor($widget['extra'] ?? []);

            return $stat->url(LeadResource::getUrl('index', $tableFilters === [] ? [] : [
                'tableFilters' => $tableFilters,
            ]));
        }, app(LeadOverviewService::class)->widgets());
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, mixed>
     */
    protected function tableFiltersFor(array $extra): array
    {
        $filters = [];

        if (! empty($extra['needs_action'])) {
            $filters['needs_action'] = ['isActive' => true];
        }

        if (! empty($extra['past_due'])) {
            $filters['past_due'] = ['isActive' => true];
        }

        if (! empty($extra['client_status'])) {
            $filters['client_status'] = [
                'values' => array_map('trim', explode(',', $extra['client_status'])),
            ];
        }

        return $filters;
    }
}
