<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Database\Eloquent\Builder;

class FileCaseStatusOverviewService
{
    /** @var list<string> */
    public const OPENED_STATUSES = ['New', 'Handling', 'Available', 'Confirmed', 'Hold'];

    /**
     * Widget keys/labels matching Ops → Files on desktop.
     *
     * @return list<array{key: string, label: string, description: string, color: string, icon: string, status: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'New',
                'label' => 'New Cases',
                'description' => 'New cases without invoices',
                'color' => 'success',
                'icon' => 'heroicon-m-document-plus',
                'status' => 'New',
            ],
            [
                'key' => 'Hold',
                'label' => 'Hold Cases',
                'description' => 'Cases on hold',
                'color' => 'warning',
                'icon' => 'heroicon-m-pause-circle',
                'status' => 'Hold',
            ],
            [
                'key' => 'Handling',
                'label' => 'Handling',
                'description' => 'Needs Action',
                'color' => 'danger',
                'icon' => 'heroicon-m-exclamation-triangle',
                'status' => 'Handling',
            ],
            [
                'key' => 'Available',
                'label' => 'Available Cases',
                'description' => 'Available cases',
                'color' => 'info',
                'icon' => 'heroicon-m-check-circle',
                'status' => 'Available',
            ],
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
        return array_map(function (array $definition) {
            $count = $this->withoutInvoices()
                ->where('status', $definition['status'])
                ->count();

            return [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'count' => $count,
                'description' => $definition['description'],
                'color' => $definition['color'],
                'icon' => $definition['icon'],
                'extra' => [
                    'status' => $definition['status'],
                    'without_invoices' => '1',
                ],
            ];
        }, self::definitions());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function applyIndexFilters(Builder $query, array $input): Builder
    {
        if (filter_var($input['without_invoices'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereDoesntHave('invoices');
        }

        $statuses = $input['statuses'] ?? null;
        if (is_array($statuses)) {
            $statuses = array_values(array_filter($statuses, fn ($status) => is_string($status) && $status !== ''));
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);

                return $query;
            }
        }

        $hasStatus = filled($input['status'] ?? null);
        $explicitOpened = array_key_exists('opened', $input) && $input['opened'] !== '' && $input['opened'] !== null;
        $opened = $explicitOpened
            ? filter_var($input['opened'], FILTER_VALIDATE_BOOLEAN)
            : ! $hasStatus;

        if ($opened) {
            $query->whereIn('status', self::OPENED_STATUSES);
        }

        return $query;
    }

    protected function withoutInvoices(): Builder
    {
        return File::query()->whereDoesntHave('invoices');
    }
}
