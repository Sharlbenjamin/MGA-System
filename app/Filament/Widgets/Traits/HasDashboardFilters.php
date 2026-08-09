<?php

namespace App\Filament\Widgets\Traits;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

trait HasDashboardFilters
{
    protected function getDashboardFilters(): array
    {
        // Try to get from Livewire properties first
        if (property_exists($this, 'selectedDuration') && property_exists($this, 'selectedMonth')) {
            return [
                'duration' => $this->selectedDuration ?? 'Month',
                'selectedMonth' => $this->selectedMonth ?? Carbon::now()->format('Y-m'),
                'selectedYear' => $this->selectedYear ?? Carbon::now()->year,
                'selectedDate' => $this->selectedDate ?? Carbon::now()->format('Y-m-d'),
            ];
        }
        
        // Get filters from the dashboard filter widget via session
        $duration = session('dashboard_duration', 'Month');
        $selectedMonth = session('dashboard_month', Carbon::now()->format('Y-m'));
        $selectedYear = session('dashboard_year', Carbon::now()->year);
        $selectedDate = session('dashboard_date', Carbon::now()->format('Y-m-d'));
        
        return [
            'duration' => $duration,
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'selectedDate' => $selectedDate,
        ];
    }

    protected function getDateRange(): array
    {
        $filters = $this->getDashboardFilters();
        
        if ($filters['duration'] === 'Day') {
            // Validate that selectedDate is a valid date string
            if (empty($filters['selectedDate']) || !is_string($filters['selectedDate'])) {
                $filters['selectedDate'] = Carbon::now()->format('Y-m-d');
            }
            
            $selectedDate = Carbon::createFromFormat('Y-m-d', $filters['selectedDate']);
            $startDate = $selectedDate->copy()->startOfDay();
            $endDate = $selectedDate->copy()->endOfDay();
            
            // Previous period for comparison
            $previousStartDate = $startDate->copy()->subDay()->startOfDay();
            $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        } elseif ($filters['duration'] === 'Month') {
            // Validate that selectedMonth is a valid month string
            if (empty($filters['selectedMonth']) || !is_string($filters['selectedMonth'])) {
                $filters['selectedMonth'] = Carbon::now()->format('Y-m');
            }
            
            $selectedDate = Carbon::createFromFormat('Y-m', $filters['selectedMonth']);
            $startDate = $selectedDate->copy()->startOfMonth();
            $endDate = $selectedDate->copy()->endOfMonth();
            
            // Previous period for comparison
            $previousStartDate = $startDate->copy()->subMonth()->startOfMonth();
            $previousEndDate = $startDate->copy()->subMonth()->endOfMonth();
        } else {
            // Validate that selectedYear is a valid year
            if (empty($filters['selectedYear']) || !is_numeric($filters['selectedYear'])) {
                $filters['selectedYear'] = Carbon::now()->year;
            }
            
            $selectedDate = Carbon::createFromDate($filters['selectedYear'], 1, 1);
            $startDate = $selectedDate->copy()->startOfYear();
            $endDate = $selectedDate->copy()->endOfYear();
            
            // Previous period for comparison
            $previousStartDate = $startDate->copy()->subYear()->startOfYear();
            $previousEndDate = $startDate->copy()->subYear()->endOfYear();
        }
        
        return [
            'current' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'previous' => [
                'start' => $previousStartDate,
                'end' => $previousEndDate,
            ],
        ];
    }

    protected function calculateComparison($currentValue, $previousValue): array
    {
        if ($previousValue == 0) {
            return [
                'percentage' => $currentValue > 0 ? 100 : 0,
                'trend' => $currentValue > 0 ? 'up' : 'neutral',
                'description' => $currentValue > 0 ? 'New data' : 'No change',
            ];
        }

        $percentage = (($currentValue - $previousValue) / abs($previousValue)) * 100;
        
        // For profit/loss scenarios, determine trend based on business logic
        $trend = 'neutral';
        if ($previousValue < 0 && $currentValue >= 0) {
            // Going from loss to profit or break-even is improvement
            $trend = 'up';
        } elseif ($previousValue >= 0 && $currentValue < 0) {
            // Going from profit/break-even to loss is decline
            $trend = 'down';
        } elseif ($previousValue >= 0 && $currentValue >= 0) {
            // Both positive - use percentage
            $trend = $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'neutral');
        } elseif ($previousValue < 0 && $currentValue < 0) {
            // Both negative - less negative is improvement
            $trend = $currentValue > $previousValue ? 'up' : ($currentValue < $previousValue ? 'down' : 'neutral');
        }
        
        return [
            'percentage' => round($percentage, 1),
            'trend' => $trend,
            'description' => $this->getComparisonDescription($currentValue, $previousValue, $percentage, $trend),
        ];
    }

    protected function getComparisonDescription($currentValue, $previousValue, $percentage, $trend): string
    {
        if ($previousValue == 0) {
            return $currentValue > 0 ? 'New data' : 'No change';
        }

        if ($previousValue < 0 && $currentValue >= 0) {
            return 'Recovered from loss';
        } elseif ($previousValue >= 0 && $currentValue < 0) {
            return 'Declined to loss';
        } elseif ($previousValue >= 0 && $currentValue >= 0) {
            // Both positive
            if ($percentage > 0) {
                return '+' . round($percentage, 1) . '% from previous period';
            } elseif ($percentage < 0) {
                return round($percentage, 1) . '% from previous period';
            } else {
                return 'No change from previous period';
            }
        } elseif ($previousValue < 0 && $currentValue < 0) {
            // Both negative
            if ($currentValue > $previousValue) {
                return 'Loss reduced by ' . round(abs($percentage), 1) . '%';
            } elseif ($currentValue < $previousValue) {
                return 'Loss increased by ' . round(abs($percentage), 1) . '%';
            } else {
                return 'No change from previous period';
            }
        }

        return 'No change from previous period';
    }

    protected function formatComparisonDescription($comparison): string
    {
        $trendIcon = match($comparison['trend']) {
            'up' => '↗',
            'down' => '↘',
            'neutral' => '→',
        };
        
        return $trendIcon . ' ' . $comparison['description'];
    }

    protected function getComparisonColor($comparison): string
    {
        return match($comparison['trend']) {
            'up' => 'success',
            'down' => 'danger',
            'neutral' => 'gray',
        };
    }

    /**
     * Cases used for the Assisted profit reference: Assisted with both invoice and bill.
     */
    protected function applyAssistedWithInvoiceAndBillScope($query)
    {
        return $query
            ->where('status', 'Assisted')
            ->whereHas('invoices')
            ->whereHas('bills');
    }

    /**
     * Hour buckets need a time component; invoice_date/bill_date are dates only,
     * so sub-day ranges attribute amounts by record created_at within that invoice/bill date.
     */
    protected function isSubDayRange(Carbon $start, Carbon $end): bool
    {
        return $start->isSameDay($end)
            && ! ($start->isStartOfDay() && $end->isEndOfDay());
    }

    protected function getRevenueBetween(Carbon $start, Carbon $end): float
    {
        $query = Invoice::query();

        if ($this->isSubDayRange($start, $end)) {
            $query->whereDate('invoice_date', $start->toDateString())
                ->whereBetween('created_at', [$start, $end]);
        } else {
            $query->whereBetween('invoice_date', [
                $start->toDateString(),
                $end->toDateString(),
            ]);
        }

        return (float) $query->sum('total_amount');
    }

    protected function getCostBetween(Carbon $start, Carbon $end): float
    {
        $query = Bill::query();

        if ($this->isSubDayRange($start, $end)) {
            $query->whereDate('bill_date', $start->toDateString())
                ->whereBetween('created_at', [$start, $end]);
        } else {
            $query->whereBetween('bill_date', [
                $start->toDateString(),
                $end->toDateString(),
            ]);
        }

        return (float) $query->sum('total_amount');
    }

    protected function getExpensesBetween(Carbon $start, Carbon $end): float
    {
        return (float) Transaction::query()
            ->where('type', 'Expense')
            ->whereDoesntHave('bills')
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
    }

    protected function getRevenueForPeriod(string $period = 'current'): float
    {
        $dateRange = $this->getDateRange();

        return $this->getRevenueBetween(
            $dateRange[$period]['start'],
            $dateRange[$period]['end'],
        );
    }

    protected function getCostForPeriod(string $period = 'current'): float
    {
        $dateRange = $this->getDateRange();

        return $this->getCostBetween(
            $dateRange[$period]['start'],
            $dateRange[$period]['end'],
        );
    }

    protected function getExpensesForPeriod(string $period = 'current'): float
    {
        $dateRange = $this->getDateRange();

        return $this->getExpensesBetween(
            $dateRange[$period]['start'],
            $dateRange[$period]['end'],
        );
    }

    /**
     * Revenue by invoice_date, cost by bill_date, expenses by transaction date.
     */
    protected function getFileBasedFinancials(string $period = 'current'): array
    {
        $revenue = $this->getRevenueForPeriod($period);
        $cost = $this->getCostForPeriod($period);
        $expenses = $this->getExpensesForPeriod($period);
        $outflow = $cost + $expenses;
        $income = $revenue - $cost;
        $profit = $income - $expenses;

        return compact('revenue', 'cost', 'expenses', 'income', 'outflow', 'profit');
    }

    /**
     * Profit for Assisted cases that have both an invoice and a bill,
     * attributed by invoice_date / bill_date in the selected period.
     */
    protected function getAssistedProfitForPeriod(string $period = 'current'): float
    {
        $dateRange = $this->getDateRange();
        $start = $dateRange[$period]['start']->toDateString();
        $end = $dateRange[$period]['end']->toDateString();

        $assistedFileScope = fn ($query) => $this->applyAssistedWithInvoiceAndBillScope($query);

        $revenue = (float) Invoice::query()
            ->whereBetween('invoice_date', [$start, $end])
            ->whereHas('file', $assistedFileScope)
            ->sum('total_amount');

        $cost = (float) Bill::query()
            ->whereBetween('bill_date', [$start, $end])
            ->whereHas('file', $assistedFileScope)
            ->sum('total_amount');

        $expenses = $this->getExpensesForPeriod($period);

        return ($revenue - $cost) - $expenses;
    }

    protected function getFileBasedChartData(string $metric): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();
        $table = $metric === 'revenue' ? 'invoices' : 'bills';
        $dateField = $metric === 'revenue' ? 'invoice_date' : 'bill_date';
        $amountField = 'total_amount';

        $query = DB::table($table)
            ->whereBetween($dateField, [
                $dateRange['current']['start']->toDateString(),
                $dateRange['current']['end']->toDateString(),
            ]);

        if ($filters['duration'] === 'Day') {
            // Date fields have no time — distribute the day's amounts by created_at hour.
            return $query
                ->selectRaw('HOUR(created_at) as bucket, SUM(' . $amountField . ') as total')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->pluck('total')
                ->toArray();
        }

        if ($filters['duration'] === 'Month') {
            return $query
                ->selectRaw('DATE(' . $dateField . ') as bucket, SUM(' . $amountField . ') as total')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->pluck('total')
                ->toArray();
        }

        return $query
            ->selectRaw('DATE_FORMAT(' . $dateField . ', "%Y-%m") as bucket, SUM(' . $amountField . ') as total')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total')
            ->toArray();
    }

    protected function getExpensesChartData(): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();

        $query = DB::table('transactions')
            ->where('type', 'Expense')
            ->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('bill_transaction')
                    ->whereColumn('bill_transaction.transaction_id', 'transactions.id');
            })
            ->whereBetween('date', [
                $dateRange['current']['start'],
                $dateRange['current']['end'],
            ]);

        if ($filters['duration'] === 'Day') {
            return $query
                ->selectRaw('HOUR(date) as bucket, SUM(amount) as total')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->pluck('total')
                ->toArray();
        }

        if ($filters['duration'] === 'Month') {
            return $query
                ->selectRaw('DATE(date) as bucket, SUM(amount) as total')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->pluck('total')
                ->toArray();
        }

        return $query
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as bucket, SUM(amount) as total')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total')
            ->toArray();
    }

    protected function getProfitForFileBucket(Carbon $start, Carbon $end): float
    {
        $revenue = $this->getRevenueBetween($start, $end);
        $cost = $this->getCostBetween($start, $end);
        $income = $revenue - $cost;
        $expenses = $this->getExpensesBetween($start, $end);

        return $income - $expenses;
    }

    protected function applyDateFilter($query, $dateField = 'created_at'): void
    {
        $dateRange = $this->getDateRange();
        $query->whereBetween($dateField, [
            $dateRange['current']['start'],
            $dateRange['current']['end']
        ]);
    }

    protected function getPreviousPeriodQuery($query, $dateField = 'created_at')
    {
        $dateRange = $this->getDateRange();
        return (clone $query)->whereBetween($dateField, [
            $dateRange['previous']['start'],
            $dateRange['previous']['end']
        ]);
    }

    // Method to listen for filter changes
    #[On('dashboard-filters-changed')]
    public function onDashboardFiltersChanged($data): void
    {
        // Store filters in session for other widgets to access
        session(['dashboard_duration' => $data['duration']]);
        session(['dashboard_month' => $data['month']]);
        session(['dashboard_year' => $data['year']]);
        session(['dashboard_date' => $data['date']]);
        
        // Refresh the widget
        $this->dispatch('$refresh');
    }
} 