<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CasesPerMonthStatus extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Cases per Month by Status';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        // Get data for the last 12 months
        $startDate = now()->subMonths(11)->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get all files within the date range
        $files = File::whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($file) {
                return $file->created_at->format('Y-m');
            });

        // Initialize arrays for each status type
        $activeCases = array_fill(0, 12, 0);
        $cancelledCases = array_fill(0, 12, 0);
        $totalCases = array_fill(0, 12, 0);
        $labels = [];

        // Process each month's data
        $currentMonth = $startDate;
        for ($i = 0; $i < 12; $i++) {
            $monthKey = $currentMonth->format('Y-m');
            $monthFiles = $files->get($monthKey, collect());

            // Count cases by status
            $activeCases[$i] = $monthFiles->whereNotIn('status', ['Cancelled', 'Void', 'Hold'])->count();
            $cancelledCases[$i] = $monthFiles->whereIn('status', ['Cancelled', 'Void', 'Hold'])->count();
            $totalCases[$i] = $monthFiles->count();

            $labels[] = $currentMonth->format('M Y');
            $currentMonth->addMonth();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active Cases',
                    'data' => $activeCases,
                    'backgroundColor' => '#22c55e', // Green
                ],
                [
                    'label' => 'Total Cases',
                    'data' => $totalCases,
                    'backgroundColor' => '#3b82f6', // Blue
                ],
                [
                    'label' => 'Cancelled Cases',
                    'data' => $cancelledCases,
                    'backgroundColor' => '#ef4444', // Red
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
