<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FileStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalFiles = File::where('created_at', '>=', now()->subMonths(1))->count();
        $assistedFiles = File::where('status', 'assisted')->where('created_at', '>=', now()->subMonths(1))->count();
        $cancelledFiles = File::where('status', 'cancelled')->where('created_at', '>=', now()->subMonths(1))->count();

        return [
            Stat::make('Assisted Files', $assistedFiles)
                ->description($assistedFiles . ' out of ' . $totalFiles . ' files')
                ->chart([50, 50, 100, 200, 200, 2000, ($assistedFiles / $totalFiles) * 100])
                ->color('success'),

            Stat::make('Cancelled Files', $cancelledFiles)
                ->description($cancelledFiles . ' out of ' . $totalFiles . ' files')
                ->chart([0, 0, 0, 0, 0, 0, ($cancelledFiles / $totalFiles) * 100])
                ->color('danger'),

            Stat::make('Pending Files', $totalFiles - $assistedFiles - $cancelledFiles)
                ->description(($totalFiles - $assistedFiles - $cancelledFiles) . ' out of ' . $totalFiles . ' files')
                ->chart([0, 0, 0, 0, 0, 0, (($totalFiles - $assistedFiles - $cancelledFiles) / $totalFiles) * 100])
                ->color('info'),
        ];
    }
}
