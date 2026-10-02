<?php

namespace App\Filament\Resources\LeadResource\Widgets;

use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ClientLeadsOverviewWidget extends BaseWidget
{
    protected ?string $heading = 'Client leads';

    protected ?string $description = 'Rejected, Black list, and Active clients stay hidden until you filter for that status.';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $visibleLeads = Lead::query()->excludingRejectedClients();
        $actionStatuses = ['Introduction', 'Reminder', 'Presentation', 'Price List', 'Contract'];

        return [
            $this->pipelineStat('Leads', (clone $visibleLeads)->count(), 'Excludes rejected, black list, and active clients'),
            $this->leadStat(
                'Needs Action',
                (clone $visibleLeads)->whereIn('leads.status', $actionStatuses)->count(),
                'Still need a follow-up',
                'warning',
                'heroicon-m-bolt',
                ['needs_action' => ['isActive' => true]],
            ),
            $this->clientStatusStat('Searching', 'danger', 'heroicon-m-magnifying-glass'),
            $this->clientStatusStat('Interested', 'warning', 'heroicon-m-hand-raised'),
            $this->clientStatusStat('Sent', 'success', 'heroicon-m-paper-airplane'),
            $this->clientStatusStat('No Reply', 'danger', 'heroicon-m-no-symbol'),
            $this->clientStatusStat('Broker', 'info', 'heroicon-m-building-office'),
            $this->leadStat(
                'Errors',
                (clone $visibleLeads)->where('leads.status', 'Error')->count(),
                'Leads marked Error',
                'danger',
                'heroicon-m-exclamation-triangle',
                ['status' => ['values' => ['Error']]],
            ),
        ];
    }

    protected function pipelineStat(string $label, int $count, string $description): Stat
    {
        return Stat::make($label, $count)
            ->description($description)
            ->descriptionIcon('heroicon-m-users')
            ->color('primary')
            ->url(LeadResource::getUrl('index'));
    }

    protected function clientStatusStat(string $status, string $color, string $icon): Stat
    {
        $query = Lead::query()->forClientStatuses([$status]);
        $leads = (clone $query)->count();
        $clients = $this->distinctClientCount(clone $query);

        return Stat::make($status, $leads)
            ->description($clients.' '.($clients === 1 ? 'client' : 'clients'))
            ->descriptionIcon($icon)
            ->color($color)
            ->url(LeadResource::getUrl('index', [
                'tableFilters' => [
                    'client_status' => [
                        'values' => [$status],
                    ],
                ],
            ]));
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     */
    protected function leadStat(string $label, int $count, string $description, string $color, string $icon, array $tableFilters): Stat
    {
        return Stat::make($label, $count)
            ->description($description)
            ->descriptionIcon($icon)
            ->color($color)
            ->url(LeadResource::getUrl('index', [
                'tableFilters' => $tableFilters,
            ]));
    }

    protected function distinctClientCount(Builder $query): int
    {
        return (int) $query->distinct()->count('leads.client_id');
    }
}
