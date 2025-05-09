<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FilesPerMonth extends ChartWidget
{
    protected static ?int $sort = 7;

    protected static ?string $heading = 'Files per Month';
    protected static string $color = 'warning';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = File::selectRaw('COUNT(*) as count, DATE_FORMAT(created_at, "%Y-%m") as month')
            ->where('created_at', '>=', now()->subYears(1))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Initialize counts array with zeros for all months
        $counts = array_fill(0, 12, 0);

        // Map the data to the correct month index (0 for Jan, 11 for Dec)
        foreach ($data as $record) {
            $monthIndex = (int)now()->parse($record->month)->format('n') - 1; // n gives 1-12, so subtract 1 for 0-11
            $counts[$monthIndex] = $record->count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Files',
                    'data' => $counts,
                    'backgroundColor' => '#197070',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }


    protected function getType(): string
    {
        return 'line';
    }
}
