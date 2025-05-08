<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use App\Models\ServiceType;

class FilesPerStatus extends ChartWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Files per Status';
    protected static ?string $maxHeight = '300px';


    protected function getData(): array
    {
        $data = File::select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();

        // Group statuses into Active and Cancelled cases
        $activeCount = $data->whereNotIn('status', ['Cancelled', 'Hold', 'Void'])->sum('count');
        $cancelledCount = $data->whereIn('status', ['Cancelled', 'Hold', 'Void'])->sum('count');
        $totalCount = $activeCount + $cancelledCount;

        // Calculate percentages
        $activePercentage = $totalCount > 0 ? round(($activeCount / $totalCount) * 100, 1) : 0;
        $cancelledPercentage = $totalCount > 0 ? round(($cancelledCount / $totalCount) * 100, 1) : 0;

        $colors = [
            '#0b5e19',  // Green for Cancelled cases
            '#9c1f11',  // Red for Active cases
        ];

        return [
            'datasets' => [
                [
                    'data' => [$activeCount, $cancelledCount],
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => [
                "Active Cases ($activePercentage%)",
                "Cancelled Cases ($cancelledPercentage%)"
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
