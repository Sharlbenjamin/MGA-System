<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Filament\Widgets\Traits\HasDashboardFilters;

class TotalFile extends ChartWidget
{
    use HasDashboardFilters;

    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }

    protected static ?int $sort = 5;

    protected static ?string $heading = 'Active Files Trend';
    protected static string $color = 'info';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();
        
        if ($filters['duration'] === 'Month') {
            // For monthly view, show daily data for the selected month
            $data = File::whereNotIn('status', ['Cancelled', 'Void', 'Hold'])
                ->whereBetween('created_at', [
                    $dateRange['current']['start'],
                    $dateRange['current']['end']
                ])
                ->selectRaw('COUNT(*) as count, DATE(created_at) as day')
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $labels = [];
            $counts = [];
            
            $currentDate = $dateRange['current']['start']->copy();
            $endDate = $dateRange['current']['end'];
            
            while ($currentDate <= $endDate) {
                $labels[] = $currentDate->format('M d');
                $dayData = $data->where('day', $currentDate->format('Y-m-d'))->first();
                $counts[] = $dayData ? $dayData->count : 0;
                $currentDate->addDay();
            }
        } else {
            // For yearly view, show monthly data for the selected year
            $data = File::whereNotIn('status', ['Cancelled', 'Void', 'Hold'])
                ->whereBetween('created_at', [
                    $dateRange['current']['start'],
                    $dateRange['current']['end']
                ])
                ->selectRaw('COUNT(*) as count, DATE_FORMAT(created_at, "%Y-%m") as month')
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $labels = [];
            $counts = [];
            
            $currentMonth = $dateRange['current']['start']->copy();
            $endMonth = $dateRange['current']['end'];
            
            while ($currentMonth <= $endMonth) {
                $labels[] = $currentMonth->format('M Y');
                $monthData = $data->where('month', $currentMonth->format('Y-m'))->first();
                $counts[] = $monthData ? $monthData->count : 0;
                $currentMonth->addMonth();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active Files',
                    'data' => $counts,
                    'backgroundColor' => '#197070',
                    'borderColor' => '#197070',
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
