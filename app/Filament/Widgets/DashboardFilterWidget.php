<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Carbon\Carbon;

class DashboardFilterWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-filter-widget';

    public ?string $selectedDuration = 'Month';
    public ?string $selectedMonth = null;
    public ?string $selectedYear = null;
    public ?string $selectedDate = null;

    protected static ?string $heading = 'Dashboard Filters';

    public function mount(): void
    {
        $this->selectedDuration = session('dashboard_duration', 'Month');
        $this->selectedMonth = session('dashboard_month', Carbon::now()->format('Y-m'));
        $this->selectedYear = session('dashboard_year', Carbon::now()->year);
        $this->selectedDate = session('dashboard_date', Carbon::now()->format('Y-m-d'));
        
        // Ensure we have a valid duration
        if (!in_array($this->selectedDuration, ['Day', 'Month', 'Year'])) {
            $this->selectedDuration = 'Month';
        }
    }

    public function getDurationOptions(): array
    {
        return [
            'Day' => 'Day',
            'Month' => 'Month',
            'Year' => 'Year',
        ];
    }

    public function getMonthOptions(): array
    {
        $currentYear = Carbon::now()->year;
        $months = [];
        
        // Generate options for the last 2 years and next year
        for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $date = Carbon::createFromDate($year, $month, 1);
                $value = $date->format('Y-m');
                $label = $date->format('F Y');
                $months[$value] = $label;
            }
        }
        
        // Sort in descending order (most recent first)
        krsort($months);
        
        return $months;
    }

    public function getYearOptions(): array
    {
        $currentYear = Carbon::now()->year;
        $years = [];
        
        // Generate options for the last 2 years and next year
        for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
            $years[$year] = $year;
        }
        
        // Sort in descending order (most recent first)
        krsort($years);
        
        return $years;
    }

    public function updatedSelectedDuration()
    {
        $this->updateFilters();
    }

    public function updatedSelectedMonth()
    {
        $this->updateFilters();
    }

    public function updatedSelectedYear()
    {
        $this->updateFilters();
    }

    public function updatedSelectedDate()
    {
        $this->updateFilters();
    }

    protected function updateFilters(): void
    {
        // Store in session
        session(['dashboard_duration' => $this->selectedDuration]);
        session(['dashboard_month' => $this->selectedMonth]);
        session(['dashboard_year' => $this->selectedYear]);
        session(['dashboard_date' => $this->selectedDate]);
        
        // Dispatch event to refresh all widgets
        $this->dispatch('dashboard-filters-changed', [
            'duration' => $this->selectedDuration,
            'month' => $this->selectedMonth,
            'year' => $this->selectedYear,
            'date' => $this->selectedDate,
        ]);
        
        // Also dispatch a general refresh event
        $this->dispatch('$refresh');
    }

    public function getSelectedDateRange(): array
    {
        if ($this->selectedDuration === 'Day') {
            $selectedDate = Carbon::createFromFormat('Y-m-d', $this->selectedDate);
            $startDate = $selectedDate->copy()->startOfDay();
            $endDate = $selectedDate->copy()->endOfDay();
            
            // Previous period for comparison
            $previousStartDate = $startDate->copy()->subDay()->startOfDay();
            $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        } elseif ($this->selectedDuration === 'Month') {
            $selectedDate = Carbon::createFromFormat('Y-m', $this->selectedMonth);
            $startDate = $selectedDate->copy()->startOfMonth();
            $endDate = $selectedDate->copy()->endOfMonth();
            
            // Previous period for comparison
            $previousStartDate = $startDate->copy()->subMonth()->startOfMonth();
            $previousEndDate = $startDate->copy()->subMonth()->endOfMonth();
        } else {
            $selectedDate = Carbon::createFromDate($this->selectedYear, 1, 1);
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
} 