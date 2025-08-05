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
        
        // Calculate widgets data - using proper relationship loading
        $invoices = $record->invoices()->with(['file.bills'])->get();
        
        // Debug: Check what we have
        $invoicesWithFiles = $invoices->filter(function($invoice) {
            return $invoice->file !== null;
        });
        
        $filesCount = $invoicesWithFiles->pluck('file_id')->unique()->count();
        $totalCost = $invoicesWithFiles->flatMap->file->flatMap->bills->sum('total_amount');
        $totalInvoices = $invoices->sum('total_amount');
        $totalProfit = $totalInvoices - $totalCost;
        
        // Alternative: Check if we should use bills directly from transaction
        $transactionBills = $record->bills()->with(['file'])->get();
        $totalCostFromBills = $transactionBills->sum('total_amount');
        
        return [
            'record' => $record,
            'filesCount' => $filesCount,
            'totalCost' => $totalCost,
            'totalProfit' => $totalProfit,
            'totalCostFromBills' => $totalCostFromBills,
        ];
    }

    public function getView(): string
    {
        return 'filament.resources.transaction-resource.pages.view-transaction';
    }
} 