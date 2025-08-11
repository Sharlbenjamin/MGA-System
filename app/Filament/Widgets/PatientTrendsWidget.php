<?php

namespace App\Filament\Widgets;

use App\Models\Patient;
use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PatientTrendsWidget extends ChartWidget
{
    protected static ?string $heading = 'Patient Trends';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $months = collect();
        $newPatients = collect();
        $newFiles = collect();

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push($date->format('M Y'));
            
            $newPatients->push(
                Patient::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count()
            );
            
            $newFiles->push(
                File::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count()
            );
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Patients',
                    'data' => $newPatients->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'New Files',
                    'data' => $newFiles->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $months->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
} 