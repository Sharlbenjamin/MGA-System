<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;

class FilesPerCountry extends ChartWidget
{
    protected static ?string $heading = 'Files per Country';
    protected static ?string $maxHeight = '300px';
    protected function getData(): array
    {
        $data = File::selectRaw('COUNT(*) as count, country_id')
            ->groupBy('country_id')
            ->get();

        return [
            'datasets' => [
                [
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#1f77b4', // blue
                        '#ff7f0e', // orange
                        '#2ca02c', // green
                        '#d62728', // red
                        '#9467bd', // purple
                        '#8c564b', // brown
                        '#e377c2', // pink
                        '#7f7f7f', // gray
                        '#bcbd22', // yellow-green
                        '#17becf', // cyan
                    ],
                ],
            ],
            'labels' => $data->pluck('country.name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

}
