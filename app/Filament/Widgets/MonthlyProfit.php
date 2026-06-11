<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use App\Filament\Widgets\Traits\HasDashboardFilters;
use Carbon\Carbon;

class MonthlyProfit extends ChartWidget
{
    use HasDashboardFilters;

    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }

    protected static ?int $sort = 3;
    protected static ?string $heading = 'Profit Trend';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();

        if ($filters['duration'] === 'Day') {
            $labels = [];
            $profits = [];

            for ($hour = 0; $hour < 24; $hour++) {
                $labels[] = sprintf('%02d:00', $hour);

                $hourStart = $dateRange['current']['start']->copy()->setHour($hour)->startOfHour();
                $hourEnd = $hourStart->copy()->endOfHour();

                $profits[] = $this->getProfitForFileBucket($hourStart, $hourEnd);
            }
        } elseif ($filters['duration'] === 'Month') {
            $labels = [];
            $profits = [];

            $currentDate = $dateRange['current']['start']->copy();
            $endDate = $dateRange['current']['end'];

            while ($currentDate <= $endDate) {
                $labels[] = $currentDate->format('M d');

                $dayStart = $currentDate->copy()->startOfDay();
                $dayEnd = $currentDate->copy()->endOfDay();

                $profits[] = $this->getProfitForFileBucket($dayStart, $dayEnd);

                $currentDate->addDay();
            }
        } else {
            $labels = [];
            $profits = [];

            $currentMonth = $dateRange['current']['start']->copy();
            $endMonth = $dateRange['current']['end'];

            while ($currentMonth <= $endMonth) {
                $labels[] = $currentMonth->format('M Y');

                $monthStart = $currentMonth->copy()->startOfMonth();
                $monthEnd = $currentMonth->copy()->endOfMonth();

                $profits[] = $this->getProfitForFileBucket($monthStart, $monthEnd);

                $currentMonth->addMonth();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Profit',
                    'data' => $profits,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
