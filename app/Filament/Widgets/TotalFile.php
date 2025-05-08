<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TotalFile extends ChartWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Active Files per Month';
    protected static string $color = 'info';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = File::whereNotIn('status', ['Cancelled', 'Void', 'Hold'])->selectRaw('COUNT(*) as count, DATE_FORMAT(created_at, "%Y-%m") as month')
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
