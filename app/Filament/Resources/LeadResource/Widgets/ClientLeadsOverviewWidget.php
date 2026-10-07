<?php

namespace App\Filament\Resources\LeadResource\Widgets;

use App\Filament\Resources\LeadResource;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ClientLeadsOverviewWidget extends BaseWidget
{
    protected ?string $heading = 'Client leads';

    protected ?string $description = 'Rejected, Black list, Active, and On Hold clients stay hidden until you filter for that status.';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $crmLeads = LeadResource::crmLeadsQuery();
        $actionStatuses = ['Introduction', 'Reminder', 'Presentation', 'Price List', 'Contract'];
        $interestedPastDue = (clone $crmLeads)
            ->forClientStatuses(['Interested'])
            ->whereDate('leads.last_contact_date', '<', Carbon::today()->subWeek()->toDateString());

        return [
            $this->pipelineStat('Leads', (clone $crmLeads)->count(), 'Client leads'),
            $this->leadStat(
                'Needs Action',
                (clone $crmLeads)->whereIn('leads.status', $actionStatuses)->count(),
                'Still need a follow-up',
                'warning',
                'heroicon-m-bolt',
                ['needs_action' => ['isActive' => true]],
            ),
            $this->clientStatusStat('Searching', 'danger', 'heroicon-m-magnifying-glass'),
            $this->leadStat(
                'Interested',
                (clone $interestedPastDue)->count(),
                $this->clientCountLabel(clone $interestedPastDue).', past due',
                'warning',
                'heroicon-m-hand-raised',
                [
                    'client_status' => ['values' => ['Interested']],
                    'past_due' => ['isActive' => true],
                ],
            ),
            $this->leadStat(
                'Follow up',
                $this->pastDueCount(),
                'Past due',
                'danger',
                'heroicon-m-clock',
                ['past_due' => ['isActive' => true]],
            ),
        ];
    }

    protected function pastDueCount(): int
    {
        return $this->pastDueLeads()->count();
    }

    protected function pastDueLeads(): Builder
    {
        return LeadResource::crmLeadsQuery()
            ->whereDate('leads.last_contact_date', '<', Carbon::today()->subWeek()->toDateString());
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
        $query = LeadResource::crmLeadsQuery()->forClientStatuses([$status]);
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

    protected function clientCountLabel(Builder $query): string
    {
        $clients = $this->distinctClientCount($query);

        return $clients.' '.($clients === 1 ? 'client' : 'clients');
    }
}
