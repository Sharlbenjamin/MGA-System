<?php

namespace App\Filament\Resources\ProviderLeadResource\Widgets;

use App\Filament\Resources\ProviderLeadResource;
use App\Models\ProviderLead;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NeedsALookWidget extends BaseWidget
{
    protected static string $view = 'filament.widgets.client-leads-overview';

    protected ?string $heading = 'Needs a look';

    protected ?string $description = 'Potential providers waiting on a next step. Sent statuses are left out. Click a status to open those leads.';

    /**
     * Statuses that still need a person to act. The matching "sent" status means that step is already done.
     *
     * @var array<int, string>
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
        $dueOn = Carbon::today()->subWeek()->toDateString();

        $counts = ProviderLead::query()
            ->select('provider_leads.status')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN provider_leads.last_contact_date IS NOT NULL AND DATE(provider_leads.last_contact_date) < ? THEN 1 ELSE 0 END) as past_due',
                [$dueOn],
            )
            ->whereIn('provider_leads.status', $this->actionStatuses)
            ->whereHas('provider', fn ($query) => $query->where('status', 'Potential'))
            ->groupBy('provider_leads.status')
            ->get()
            ->keyBy('status');

        $stats = [];

        foreach ($this->actionStatuses as $status) {
            $row = $counts->get($status);
            $total = (int) ($row->total ?? 0);

            if ($total === 0) {
                continue;
            }

            $pastDue = (int) ($row->past_due ?? 0);

            $stats[] = Stat::make($status, $total)
                ->description($pastDue > 0 ? $pastDue.' past due' : 'Waiting on a next step')
                ->descriptionIcon($pastDue > 0 ? 'heroicon-m-clock' : 'heroicon-m-bolt')
                ->color($pastDue > 0 ? 'danger' : $this->colorFor($status))
                ->url(ProviderLeadResource::getUrl('index', [
                    'tableFilters' => [
                        'provider_status' => [
                            'values' => ['Potential'],
                        ],
                        'status' => [
                            'values' => [$status],
                        ],
                    ],
                ]));
        }

        if ($stats === []) {
            return [
                Stat::make('Needs a look', 0)
                    ->description('Nothing is waiting')
                    ->descriptionIcon('heroicon-m-check')
                    ->color('success'),
            ];
        }

        return $stats;
    }

    protected function colorFor(string $status): string
    {
        return match ($status) {
            'Cancel Case', 'Fake Case' => 'danger',
            'Contract' => 'success',
            'Pending information' => 'gray',
            'Reminder', 'Discount' => 'warning',
            default => 'info',
        };
    }
}
