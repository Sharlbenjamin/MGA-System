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
            'invoices.file.patient.client',
            'bills.file.patient.client',
            'bills.provider',
            'bills.providerBranch',
            'bankAccount'
        ]);
        
        // Calculate widgets data - using proper relationship loading
        $invoices = $record->invoices()->with(['file.bills'])->get();
        
        // Debug: Check what we have
        $invoicesWithFiles = $invoices->filter(function($invoice) {
            return $invoice->file !== null;
        });
        
        $filesCount = $invoicesWithFiles->pluck('file_id')->unique()->count();
        
        // Calculate total cost by iterating through invoices manually
        $totalCost = 0;
        foreach ($invoicesWithFiles as $invoice) {
            if ($invoice->file && $invoice->file->bills) {
                $totalCost += $invoice->file->bills->sum('total_amount');
            }
        }
        
        $totalInvoices = $invoices->sum('total_amount');
        $totalProfit = $totalInvoices - $totalCost;
        
        return [
            'record' => $record,
            'filesCount' => $filesCount,
            'totalCost' => $totalCost,
            'totalProfit' => $totalProfit,
            'totalInvoices' => $totalInvoices,
        ];
    }

    public function getView(): string
    {
        return 'filament.resources.transaction-resource.pages.view-transaction';
    }
} 