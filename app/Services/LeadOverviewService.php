<?php

namespace App\Services;

use App\Filament\Resources\LeadResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class LeadOverviewService
{
    /**
     * Lead statuses that still need a follow-up (a matching "Sent" status means that step is done).
     *
     * @var list<string>
     */
    public const ACTION_STATUSES = ['Introduction', 'Reminder', 'Presentation', 'Price List', 'Contract'];

    /**
     * Widget keys/labels matching CRM → Leads on desktop.
     *
     * @return list<array{key: string, label: string, color: string, icon: string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'leads', 'label' => 'Leads', 'color' => 'primary', 'icon' => 'heroicon-m-users'],
            ['key' => 'needs_action', 'label' => 'Needs Action', 'color' => 'warning', 'icon' => 'heroicon-m-bolt'],
            ['key' => 'searching', 'label' => 'Searching', 'color' => 'danger', 'icon' => 'heroicon-m-magnifying-glass'],
            ['key' => 'interested', 'label' => 'Interested', 'color' => 'warning', 'icon' => 'heroicon-m-hand-raised'],
            ['key' => 'follow_up', 'label' => 'Follow up', 'color' => 'danger', 'icon' => 'heroicon-m-clock'],
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     count: int,
     *     description: string,
     *     color: string,
     *     icon: string,
     *     extra: array<string, string>
     * }>
     */
    public function widgets(): array
    {
        $crmLeads = LeadResource::crmLeadsQuery();
        $searching = (clone $crmLeads)->forClientStatuses(['Searching']);
        $interestedPastDue = (clone $crmLeads)
            ->forClientStatuses(['Interested'])
            ->whereDate('leads.last_contact_date', '<', $this->pastDueOn());
        $needsAction = (clone $crmLeads)->whereIn('leads.status', self::ACTION_STATUSES);
        $followUp = (clone $crmLeads)->whereDate('leads.last_contact_date', '<', $this->pastDueOn());

        $byKey = [
            'leads' => [
                'count' => (clone $crmLeads)->count(),
                'description' => 'Client leads',
                'extra' => [],
            ],
            'needs_action' => [
                'count' => $needsAction->count(),
                'description' => 'Still need a follow-up',
                'extra' => ['needs_action' => '1'],
            ],
            'searching' => [
                'count' => (clone $searching)->count(),
                'description' => $this->clientCountLabel(clone $searching),
                'extra' => ['client_status' => 'Searching'],
            ],
            'interested' => [
                'count' => (clone $interestedPastDue)->count(),
                'description' => $this->clientCountLabel(clone $interestedPastDue).', past due',
                'extra' => ['client_status' => 'Interested', 'past_due' => '1'],
            ],
            'follow_up' => [
                'count' => $followUp->count(),
                'description' => 'Past due',
                'extra' => ['past_due' => '1'],
            ],
        ];

        return array_map(function (array $definition) use ($byKey) {
            $stats = $byKey[$definition['key']];

            return [
                ...$definition,
                'count' => $stats['count'],
                'description' => $stats['description'],
                'extra' => $stats['extra'],
            ];
        }, self::definitions());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function applyIndexFilters(Builder $query, array $input): Builder
    {
        $clientStatus = trim((string) ($input['client_status'] ?? ''));
        $hasClientStatus = $clientStatus !== '';
        $crm = array_key_exists('crm', $input) && $input['crm'] !== '' && $input['crm'] !== null
            ? filter_var($input['crm'], FILTER_VALIDATE_BOOLEAN)
            : ! $hasClientStatus;

        if ($crm) {
            $query->hiddenFromCrmUntilFiltered();
        }

        if ($hasClientStatus) {
            $query->forClientStatuses(array_map('trim', explode(',', $clientStatus)));
        }

        if (filter_var($input['needs_action'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('leads.status', self::ACTION_STATUSES);
        }

        if (filter_var($input['past_due'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereDate('leads.last_contact_date', '<', $this->pastDueOn());
        }

        $followUp = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($input['follow_up'] ?? '')),
        )));

        if ($followUp !== []) {
            $dueOn = $this->pastDueOn();
            $query->where(function (Builder $inner) use ($followUp, $dueOn): void {
                foreach ($followUp as $value) {
                    $inner->orWhere(function (Builder $part) use ($value, $dueOn): void {
                        if ($value === 'overdue') {
                            $part->whereDate('leads.last_contact_date', '<', $dueOn);
                        } elseif ($value === 'due_today') {
                            $part->whereDate('leads.last_contact_date', '=', $dueOn);
                        } elseif ($value === 'remaining') {
                            $part->whereDate('leads.last_contact_date', '>', $dueOn);
                        } elseif ($value === 'no_date') {
                            $part->whereNull('leads.last_contact_date');
                        }
                    });
                }
            });
        }

        return $query;
    }

    public function pastDueOn(): string
    {
        return Carbon::today()->subWeek()->toDateString();
    }

    protected function clientCountLabel(Builder $query): string
    {
        $clients = (int) $query->distinct()->count('leads.client_id');

        return $clients.' '.($clients === 1 ? 'client' : 'clients');
    }
}
