<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class FilesPerServiceType extends ChartWidget
{
    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Files per Service Type';
    protected static ?string $maxHeight = '300px';


    protected function getData(): array
    {
        $data = File::with('serviceType')
            ->select('service_type_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('service_type_id')
            ->get()
            ->map(function ($item) {
                return [
                    'count' => $item->count,
                    'name' => $item->serviceType->name
                ];
            });

        $colors = [
            '#197070',  // Teal
            '#1A5F7A',  // Steel Blue
            '#86A789',  // Sage Green
            '#C7B7A3',  // Warm Beige
            '#B67352',  // Terracotta
            '#6B240C',  // Rustic Brown
            '#994D1C',  // Burnt Orange
            '#435334',  // Forest Green
        ];

        // Repeat colors if we have more service types than colors
        $backgroundColors = array_map(
            fn ($index) => $colors[$index % count($colors)],
            range(0, $data->count() - 1)
        );

        return [
            'datasets' => [
                [
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => $backgroundColors,
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
