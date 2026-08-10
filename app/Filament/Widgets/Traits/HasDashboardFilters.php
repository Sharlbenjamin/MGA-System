<?php

namespace App\Filament\Widgets\Traits;

use App\Models\Bill;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
     * Files created in the given range — the base set for revenue/cost attribution.
     */
    protected function selectedFilesQuery(Carbon $start, Carbon $end): Builder
    {
        return File::query()->whereBetween('created_at', [$start, $end]);
    }

    protected function selectedFileIdsSubquery(Carbon $start, Carbon $end)
    {
        return $this->selectedFilesQuery($start, $end)->select('id');
    }

    protected function getRevenueBetween(Carbon $start, Carbon $end): float
    {
        return (float) Invoice::query()
            ->whereIn('file_id', $this->selectedFileIdsSubquery($start, $end))
            ->sum('total_amount');
    }

    protected function getCostBetween(Carbon $start, Carbon $end): float
    {
        return (float) Bill::query()
            ->whereIn('file_id', $this->selectedFileIdsSubquery($start, $end))
            ->sum('total_amount');
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
     * Selected files by created_at: revenue/cost from their invoices/bills;
     * expenses by transaction date (type Expense); income/profit/outflow derived.
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
     * Sum of invoice totals (Paid or Partial) for files created in the period.
     */
    protected function getPaidPartialInvoicesForPeriod(string $period = 'current'): float
    {
        $dateRange = $this->getDateRange();

        return (float) Invoice::query()
            ->whereIn('status', ['Paid', 'Partial'])
            ->whereIn('file_id', $this->selectedFileIdsSubquery(
                $dateRange[$period]['start'],
                $dateRange[$period]['end'],
            ))
            ->sum('total_amount');
    }

    protected function getFileBasedChartData(string $metric): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();
        $table = $metric === 'revenue' ? 'invoices' : 'bills';
        $amountField = $table . '.total_amount';

        $query = DB::table($table)
            ->join('files', 'files.id', '=', $table . '.file_id')
            ->whereBetween('files.created_at', [
                $dateRange['current']['start'],
                $dateRange['current']['end'],
            ]);

        if ($filters['duration'] === 'Day') {
            return $query
                ->selectRaw('HOUR(files.created_at) as bucket, SUM(' . $amountField . ') as total')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->pluck('total')
                ->toArray();
        }

        if ($filters['duration'] === 'Month') {
            return $query
                ->selectRaw('DATE(files.created_at) as bucket, SUM(' . $amountField . ') as total')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->pluck('total')
                ->toArray();
        }

        return $query
            ->selectRaw('DATE_FORMAT(files.created_at, "%Y-%m") as bucket, SUM(' . $amountField . ') as total')
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