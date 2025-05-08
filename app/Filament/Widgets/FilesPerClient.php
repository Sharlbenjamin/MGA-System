<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;

class FilesPerClient extends ChartWidget
{
    protected static ?int $sort = 9;

    protected static ?string $heading = 'Files per Client';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = File::selectRaw('COUNT(*) as count, clients.id as client_id, clients.company_name as client_name')
            ->join('patients', 'files.patient_id', '=', 'patients.id')
            ->join('clients', 'patients.client_id', '=', 'clients.id')
            ->groupBy('clients.id', 'clients.company_name')
            ->get();

        return [
            'datasets' => [
                [
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                    ],
                ],
            ],
            'labels' => $data->pluck('client_name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
