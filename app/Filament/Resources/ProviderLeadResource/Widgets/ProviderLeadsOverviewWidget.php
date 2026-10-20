<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Filament\Resources\ProviderLeadResource;
use App\Models\Provider;
use App\Models\ProviderLead;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ProviderLeadsOverviewWidget extends BaseWidget
{
    protected static string $view = 'filament.widgets.client-leads-overview';

    protected ?string $heading = 'Provider leads';

    protected ?string $description = 'Potential and Searching show lead counts by provider status. Needs Action and Follow up cover Potential providers. Contacted this week uses the interactions table.';

    /**
     * Lead statuses that still need a next step (matching "sent" means that step is done).
     *
     * @var list<string>
     */
    protected array $actionStatuses = [
        'Pending information',
        'Step one',
        'Reminder',
        'Discount',
        'Step two',
        'Presentation',
        'Contract',
        'Fake Case',
        'Cancel Case',
    ];

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        return [
            $this->providerStatusStat('Potential', 'info', 'heroicon-m-sparkles', hideErrorLeads: true),
            $this->providerStatusStat('Searching', 'gray', 'heroicon-m-magnifying-glass', hideErrorLeads: false),
            $this->leadStat(
                'Needs Action',
                $this->needsActionLeads()->count(),
                'Still need a next step',
                'warning',
                'heroicon-m-bolt',
                [
                    'provider_status' => ['values' => ['Potential']],
                    'needs_action' => ['isActive' => true],
                ],
            ),
            $this->leadStat(
                'Follow up',
                $this->followUpLeads()->count(),
                'Past due',
                'danger',
                'heroicon-m-clock',
                [
                    'provider_status' => ['values' => ['Potential']],
                    'past_due' => ['isActive' => true],
                ],
            ),
            $this->leadStat(
                'Contacted this week',
                $this->contactedThisWeekLeads()->count(),
                'From interactions',
                'success',
                'heroicon-m-chat-bubble-left-right',
                [
                    'contacted_this_week' => ['isActive' => true],
                ],
            ),
        ];
    }

    protected function providerStatusStat(string $status, string $color, string $icon, bool $hideErrorLeads): Stat
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

    /**
     * @param  array<string, mixed>  $tableFilters
     */
    protected function leadStat(
        string $label,
        int $count,
        string $description,
        string $color,
        string $icon,
        array $tableFilters,
    ): Stat {
        return Stat::make($label, $count)
            ->description($description)
            ->descriptionIcon($icon)
            ->color($color)
            ->url(ProviderLeadResource::getUrl('index', [
                'tableFilters' => $tableFilters,
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

    protected function needsActionLeads(): Builder
    {
        return ProviderLead::query()
            ->whereIn('provider_leads.status', $this->actionStatuses)
            ->whereHas('provider', fn (Builder $query) => $query->where('status', 'Potential'));
    }

    protected function followUpLeads(): Builder
    {
        return ProviderLead::query()
            ->whereDate('provider_leads.last_contact_date', '<', Carbon::today()->subWeek()->toDateString())
            ->whereHas('provider', fn (Builder $query) => $query->where('status', 'Potential'))
            ->whereRaw('LOWER(provider_leads.status) != ?', ['error']);
    }

    protected function contactedThisWeekLeads(): Builder
    {
        [$startOfWeek, $endOfWeek] = $this->currentWeekRange();

        return ProviderLead::query()
            ->whereHas('interactions', function (Builder $query) use ($startOfWeek, $endOfWeek): void {
                $query
                    ->whereDate('interactions.interaction_date', '>=', $startOfWeek->toDateString())
                    ->whereDate('interactions.interaction_date', '<=', $endOfWeek->toDateString());
            });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function currentWeekRange(): array
    {
        return [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ];
    }
}
