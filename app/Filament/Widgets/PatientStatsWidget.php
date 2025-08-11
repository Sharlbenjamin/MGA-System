<?php

namespace App\Filament\Widgets;

use App\Models\Patient;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PatientStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalPatients = Patient::count();
        $patientsWithFiles = Patient::has('files')->count();
        $patientsWithOutstandingFinancials = Patient::whereHas('invoices', function ($query) {
            $query->whereRaw('total_amount > paid_amount');
        })->count();
        
        $averageFilesPerPatient = $totalPatients > 0 ? round(Patient::withCount('files')->get()->avg('files_count'), 1) : 0;
        
        $recentPatients = Patient::where('created_at', '>=', now()->subDays(30))->count();
        
        $totalInvoices = Patient::withSum('invoices', 'total_amount')->get()->sum('invoices_sum_total_amount') ?? 0;
        $totalPaidInvoices = Patient::withSum('invoices', 'paid_amount')->get()->sum('invoices_sum_paid_amount') ?? 0;
        $outstandingInvoices = $totalInvoices - $totalPaidInvoices;

        return [
            Stat::make('Total Patients', $totalPatients)
                ->description('All patients in the system')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Patients with Files', $patientsWithFiles)
                ->description("{$averageFilesPerPatient} avg files per patient")
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('success'),

            Stat::make('Patients with Outstanding', $patientsWithOutstandingFinancials)
                ->description('Have unpaid invoices')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('New Patients (30 days)', $recentPatients)
                ->description('Added in the last 30 days')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Total Outstanding', '$' . number_format($outstandingInvoices, 2))
                ->description('Total unpaid invoices')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('danger'),
        ];
    }
} 