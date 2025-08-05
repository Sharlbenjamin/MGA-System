<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getViewData(): array
    {
        // Load the record with all necessary relationships
        $record = $this->record->load([
            'invoices.file.bills',
            'bills.file',
            'bankAccount'
        ]);
        
        // Calculate widgets data
        $filesCount = $record->invoices->pluck('file_id')->unique()->count();
        $totalCost = $record->invoices->flatMap->file->flatMap->bills->sum('total_amount');
        $totalInvoices = $record->invoices->sum('total_amount');
        $totalProfit = $totalInvoices - $totalCost;
        
        return [
            'record' => $record,
            'filesCount' => $filesCount,
            'totalCost' => $totalCost,
            'totalProfit' => $totalProfit,
        ];
    }

    public function getView(): string
    {
        return 'filament.resources.transaction-resource.pages.view-transaction';
    }
} 