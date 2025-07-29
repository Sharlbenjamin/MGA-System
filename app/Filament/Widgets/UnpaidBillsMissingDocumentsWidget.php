<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UnpaidBillsMissingDocumentsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        // Bills with missing Google Drive links
        $billsMissingDocuments = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->where(function ($query) {
                $query->whereNull('bill_google_link')
                      ->orWhere('bill_google_link', '');
            })
            ->count();

        // Amount of bills with missing documents
        $missingDocumentsAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->where(function ($query) {
                $query->whereNull('bill_google_link')
                      ->orWhere('bill_google_link', '');
            })
            ->sum(DB::raw('total_amount - paid_amount'));

        // Bills with missing documents and bank accounts
        $missingDocsWithBankAccount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereNotNull('bank_account_id')
            ->where(function ($query) {
                $query->whereNull('bill_google_link')
                      ->orWhere('bill_google_link', '');
            })
            ->count();

        // Average amount for bills with missing documents
        $averageMissingDocsAmount = $billsMissingDocuments > 0 
            ? $missingDocumentsAmount / $billsMissingDocuments 
            : 0;

        return [
            Stat::make('Bills Missing Documents', $billsMissingDocuments)
                ->description('Bills without Google Drive links')
                ->descriptionIcon('heroicon-m-document-minus')
                ->color('danger'),

            Stat::make('Missing Documents Amount', '€' . number_format($missingDocumentsAmount, 2))
                ->description('Total amount for bills missing documents')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('danger'),

            Stat::make('Missing Docs with Bank Account', $missingDocsWithBankAccount)
                ->description('Missing documents with bank accounts')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('warning'),

            Stat::make('Average Missing Docs Amount', '€' . number_format($averageMissingDocsAmount, 2))
                ->description('Average amount per missing document bill')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }
} 