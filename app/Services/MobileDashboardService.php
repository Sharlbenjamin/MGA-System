<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\File;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MobileDashboardService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function forUser(User $user, array $input): array
    {
        $filters = $this->normalizeFilters($input);
        $range = $this->dateRange($filters);
        $canFinance = $this->userCanSeeFinancials($user);

        $payload = [
            'filters' => $filters,
            'kpis' => $this->kpiCards($user),
            'charts' => [
                'case_volume' => $this->caseVolumeChart($filters, $range),
                'case_activity' => $this->caseActivitySeries($filters, $range),
            ],
            'progress' => $this->caseCompletionCards($range),
        ];

        if (! $canFinance) {
            $payload['hero'] = null;

            return $payload;
        }

        $current = $this->fileFinancials($range['current']);
        $previous = $this->fileFinancials($range['previous']);

        $payload['hero'] = [
            'invoiced_total' => round($current['revenue'], 2),
            'billed_total' => round($current['cost'], 2),
            'difference' => round($current['income'], 2),
            'difference_label' => 'Invoiced minus billed (cases created in period)',
            'invoiced_comparison' => $this->comparison($current['revenue'], $previous['revenue']),
            'billed_comparison' => $this->comparison($current['cost'], $previous['cost']),
            'difference_comparison' => $this->comparison($current['income'], $previous['income']),
        ];
        $payload['charts']['invoiced_vs_billed'] = $this->invoicedVsBilledSeries($filters, $range);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{duration: string, month: string, year: int, date: string}
     */
    protected function normalizeFilters(array $input): array
    {
        $duration = in_array($input['duration'] ?? '', ['Day', 'Month', 'Year'], true)
            ? $input['duration']
            : 'Month';

        return [
            'duration' => $duration,
            'month' => is_string($input['month'] ?? null) ? $input['month'] : Carbon::now()->format('Y-m'),
            'year' => (int) ($input['year'] ?? Carbon::now()->year),
            'date' => is_string($input['date'] ?? null) ? $input['date'] : Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * @param  array{duration: string, month: string, year: int, date: string}  $filters
     * @return array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}
     */
    protected function dateRange(array $filters): array
    {
        if ($filters['duration'] === 'Day') {
            $start = Carbon::createFromFormat('Y-m-d', $filters['date'])->startOfDay();
            $end = $start->copy()->endOfDay();
            $prevStart = $start->copy()->subDay()->startOfDay();
            $prevEnd = $start->copy()->subDay()->endOfDay();
        } elseif ($filters['duration'] === 'Year') {
            $start = Carbon::createFromDate($filters['year'], 1, 1)->startOfYear();
            $end = $start->copy()->endOfYear();
            $prevStart = $start->copy()->subYear()->startOfYear();
            $prevEnd = $start->copy()->subYear()->endOfYear();
        } else {
            $start = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $prevStart = $start->copy()->subMonth()->startOfMonth();
            $prevEnd = $start->copy()->subMonth()->endOfMonth();
        }

        return [
            'current' => ['start' => $start, 'end' => $end],
            'previous' => ['start' => $prevStart, 'end' => $prevEnd],
        ];
    }

    protected function userCanSeeFinancials(User $user): bool
    {
        if ($user->roles->contains(fn ($role) => strtolower($role->name) === 'admin')) {
            return true;
        }

        return $user->can('view Invoice') && $user->can('view Bill');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function kpiCards(User $user): array
    {
        $cards = [];

        if ($user->can('view File')) {
            $cards[] = [
                'key' => 'opened_cases',
                'label' => 'Opened cases',
                'value' => File::query()->whereIn('status', FileCaseStatusOverviewService::OPENED_STATUSES)->count(),
                'route' => 'cases',
                'filter' => 'opened',
            ];
            $cards[] = [
                'key' => 'assisted_cases',
                'label' => 'Assisted cases',
                'value' => File::query()->where('status', 'Assisted')->count(),
                'route' => 'cases',
                'filter' => 'assisted',
            ];
        }

        if ($user->can('view Invoice')) {
            $cards[] = [
                'key' => 'outstanding_invoices',
                'label' => 'Outstanding invoices',
                'value' => round((float) Invoice::query()->whereIn('status', ['Unpaid', 'Partial', 'Sent', 'Posted'])->sum('total_amount'), 2),
                'format' => 'money',
                'route' => 'finance',
                'filter' => 'invoices_outstanding',
            ];
        }

        if ($user->can('view Bill')) {
            $cards[] = [
                'key' => 'outstanding_bills',
                'label' => 'Outstanding bills',
                'value' => round((float) Bill::query()->whereIn('status', ['Unpaid', 'Partial'])->sum('total_amount'), 2),
                'format' => 'money',
                'route' => 'finance',
                'filter' => 'bills_outstanding',
            ];
        }

        return $cards;
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $period
     * @return array{revenue: float, cost: float, income: float}
     */
    protected function fileFinancials(array $period): array
    {
        $fileIds = File::query()
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->select('id');

        $revenue = (float) Invoice::query()->whereIn('file_id', $fileIds)->sum('total_amount');
        $cost = (float) Bill::query()->whereIn('file_id', $fileIds)->sum('total_amount');

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'income' => $revenue - $cost,
        ];
    }

    /**
     * @return array{percentage: float, trend: string, description: string}
     */
    protected function comparison(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'trend' => $current > 0 ? 'up' : 'neutral',
                'description' => $current > 0 ? 'New data' : 'No change',
            ];
        }

        $percentage = (($current - $previous) / abs($previous)) * 100;
        $trend = $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'neutral');

        return [
            'percentage' => round($percentage, 1),
            'trend' => $trend,
            'description' => ($percentage >= 0 ? '+' : '').round($percentage, 1).'% vs previous period',
        ];
    }

    /**
     * @param  array{duration: string, month: string, year: int, date: string}  $filters
     * @param  array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}  $range
     * @return array<string, mixed>
     */
    protected function caseVolumeChart(array $filters, array $range): array
    {
        $series = $this->caseVolumeSeries($filters, $range);
        $currentTotal = (int) File::query()
            ->whereBetween('created_at', [$range['current']['start'], $range['current']['end']])
            ->count();
        $previousTotal = (int) File::query()
            ->whereBetween('created_at', [$range['previous']['start'], $range['previous']['end']])
            ->count();

        return array_merge($series, [
            'period_total' => $currentTotal,
            'period_comparison' => $this->comparison((float) $currentTotal, (float) $previousTotal),
        ]);
    }

    /**
     * @param  array{duration: string, month: string, year: int, date: string}  $filters
     * @param  array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}  $range
     */
    protected function caseVolumeSeries(array $filters, array $range): array
    {
        [$labels, $bucketType] = $this->buildLabels($filters, $range);
        $values = array_fill(0, count($labels), 0);

        $select = match ($bucketType) {
            'hour' => 'HOUR(created_at)',
            'day' => 'DATE(created_at)',
            default => 'DATE_FORMAT(created_at, "%Y-%m")',
        };

        $rows = File::query()
            ->whereBetween('created_at', [$range['current']['start'], $range['current']['end']])
            ->selectRaw($select.' as bucket, COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        foreach ($labels as $index => $label) {
            $key = $this->bucketKey($bucketType, $index, $range);
            $values[$index] = (int) ($rows[(string) $key] ?? $rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  array{duration: string, month: string, year: int, date: string}  $filters
     * @param  array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}  $range
     */
    protected function invoicedVsBilledSeries(array $filters, array $range): array
    {
        [$labels, $bucketType] = $this->buildLabels($filters, $range);
        $invoiced = array_fill(0, count($labels), 0.0);
        $billed = array_fill(0, count($labels), 0.0);
        $select = match ($bucketType) {
            'hour' => 'HOUR(files.created_at)',
            'day' => 'DATE(files.created_at)',
            default => 'DATE_FORMAT(files.created_at, "%Y-%m")',
        };

        $invoiceRows = DB::table('invoices')
            ->join('files', 'files.id', '=', 'invoices.file_id')
            ->whereBetween('files.created_at', [$range['current']['start'], $range['current']['end']])
            ->selectRaw($select.' as bucket, SUM(invoices.total_amount) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $billRows = DB::table('bills')
            ->join('files', 'files.id', '=', 'bills.file_id')
            ->whereBetween('files.created_at', [$range['current']['start'], $range['current']['end']])
            ->selectRaw($select.' as bucket, SUM(bills.total_amount) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        foreach ($labels as $index => $label) {
            $key = (string) $this->bucketKey($bucketType, $index, $range);
            $invoiced[$index] = round((float) ($invoiceRows[$key] ?? 0), 2);
            $billed[$index] = round((float) ($billRows[$key] ?? 0), 2);
        }

        return ['labels' => $labels, 'invoiced' => $invoiced, 'billed' => $billed];
    }

    /**
     * @param  array{duration: string, month: string, year: int, date: string}  $filters
     * @param  array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}  $range
     */
    protected function caseActivitySeries(array $filters, array $range): array
    {
        [$labels, $bucketType] = $this->buildLabels($filters, $range);
        $active = array_fill(0, count($labels), 0);
        $assisted = array_fill(0, count($labels), 0);
        $open = FileCaseStatusOverviewService::OPENED_STATUSES;

        $select = match ($bucketType) {
            'hour' => 'HOUR(created_at)',
            'day' => 'DATE(created_at)',
            default => 'DATE_FORMAT(created_at, "%Y-%m")',
        };

        $rows = File::query()
            ->whereBetween('created_at', [$range['current']['start'], $range['current']['end']])
            ->selectRaw($select.' as bucket, status, COUNT(*) as total')
            ->groupBy('bucket', 'status')
            ->get();

        foreach ($labels as $index => $label) {
            $key = (string) $this->bucketKey($bucketType, $index, $range);
            foreach ($rows as $row) {
                if ((string) $row->bucket !== $key) {
                    continue;
                }
                if (in_array($row->status, $open, true)) {
                    $active[$index] += (int) $row->total;
                }
                if ($row->status === 'Assisted') {
                    $assisted[$index] += (int) $row->total;
                }
            }
        }

        return ['labels' => $labels, 'active' => $active, 'assisted' => $assisted];
    }

    /**
     * @param  array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}  $range
     * @return list<array<string, mixed>>
     */
    protected function caseCompletionCards(array $range): array
    {
        $total = File::query()
            ->whereBetween('created_at', [$range['current']['start'], $range['current']['end']])
            ->where('status', '!=', 'Void')
            ->count();

        $assisted = File::query()
            ->whereBetween('created_at', [$range['current']['start'], $range['current']['end']])
            ->where('status', 'Assisted')
            ->count();

        $withInvoice = File::query()
            ->whereBetween('created_at', [$range['current']['start'], $range['current']['end']])
            ->whereHas('invoices')
            ->count();

        $den = max($total, 1);

        return [
            ['key' => 'assisted_in_period', 'label' => 'Assisted in period', 'numerator' => $assisted, 'denominator' => $den],
            ['key' => 'invoiced_in_period', 'label' => 'Cases with invoice', 'numerator' => $withInvoice, 'denominator' => $den],
        ];
    }

    /**
     * @param  array{duration: string, month: string, year: int, date: string}  $filters
     * @param  array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}  $range
     * @return array{0: list<string>, 1: string}
     */
    protected function buildLabels(array $filters, array $range): array
    {
        $labels = [];
        if ($filters['duration'] === 'Day') {
            for ($h = 0; $h < 24; $h++) {
                $labels[] = sprintf('%02d:00', $h);
            }

            return [$labels, 'hour'];
        }
        if ($filters['duration'] === 'Year') {
            $cursor = $range['current']['start']->copy()->startOfMonth();
            $end = $range['current']['end']->copy()->startOfMonth();
            while ($cursor <= $end) {
                $labels[] = $cursor->format('M Y');
                $cursor->addMonth();
            }

            return [$labels, 'month'];
        }

        $cursor = $range['current']['start']->copy();
        $end = $range['current']['end']->copy();
        while ($cursor <= $end) {
            $labels[] = $cursor->format('M d');
            $cursor->addDay();
        }

        return [$labels, 'day'];
    }

    /**
     * @param  array{current: array{start: Carbon, end: Carbon}, previous: array{start: Carbon, end: Carbon}}  $range
     */
    protected function bucketKey(string $type, int $index, array $range): string|int
    {
        if ($type === 'hour') {
            return $index;
        }
        if ($type === 'day') {
            return $range['current']['start']->copy()->addDays($index)->format('Y-m-d');
        }

        return $range['current']['start']->copy()->addMonths($index)->format('Y-m');
    }
}
