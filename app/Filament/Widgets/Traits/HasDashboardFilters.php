<?php

namespace App\Filament\Widgets\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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
            $selectedDate = Carbon::createFromFormat('Y-m-d', $filters['selectedDate']);
            $startDate = $selectedDate->copy()->startOfDay();
            $endDate = $selectedDate->copy()->endOfDay();
            
            // Previous period for comparison
            $previousStartDate = $startDate->copy()->subDay()->startOfDay();
            $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        } elseif ($filters['duration'] === 'Month') {
            $selectedDate = Carbon::createFromFormat('Y-m', $filters['selectedMonth']);
            $startDate = $selectedDate->copy()->startOfMonth();
            $endDate = $selectedDate->copy()->endOfMonth();
            
            // Previous period for comparison
            $previousStartDate = $startDate->copy()->subMonth()->startOfMonth();
            $previousEndDate = $startDate->copy()->subMonth()->endOfMonth();
        } else {
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

        $percentage = (($currentValue - $previousValue) / $previousValue) * 100;
        
        return [
            'percentage' => round($percentage, 1),
            'trend' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'neutral'),
            'description' => $percentage > 0 
                ? '+' . round($percentage, 1) . '% from previous period'
                : ($percentage < 0 
                    ? round($percentage, 1) . '% from previous period'
                    : 'No change from previous period'),
        ];
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