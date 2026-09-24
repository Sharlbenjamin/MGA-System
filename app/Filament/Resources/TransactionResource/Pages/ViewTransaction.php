<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionSendProofAction;
use App\Models\BankAccount;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    public function getBreadcrumbs(): array
    {
        return TransactionResource::recordBreadcrumbs($this->record, $this->getTitle());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Trx'),
            Action::make('viewTrxInPdf')
                ->label('View Trx In PDF')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => $this->record->getTrxInPdfUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->getTrxInPdfUrl()),
            Action::make('viewTrxOutPdf')
                ->label('View Trx Out PDF')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => $this->record->getTrxOutPdfUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->getTrxOutPdfUrl()),
            Action::make('viewDocument')
                ->label('View Document')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => $this->record->getAttachmentUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->getAttachmentUrl()),
            TransactionSendProofAction::make(),
        ];
    }

    protected function getViewData(): array
    {
        // Load the record with all necessary relationships
        $record = $this->record->load([
            'invoices.file.patient.client',
            'bills.file.patient.client',
            'bills.provider.bankAccounts.country',
            'bills.branch.bankAccounts.country',
            'bills.branch.provider.bankAccounts.country',
            'bankAccount',
        ]);

        // Calculate widgets data - using proper relationship loading
        $invoices = $record->invoices()->with(['file.bills'])->get();

        // Debug: Check what we have
        $invoicesWithFiles = $invoices->filter(function ($invoice) {
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
            'providerBankAccount' => $this->resolveProviderBankAccount($record),
        ];
    }

    public function getView(): string
    {
        return 'filament.resources.transaction-resource.pages.view-transaction';
    }

    protected function resolveProviderBankAccount(Transaction $transaction): ?BankAccount
    {
        $account = TransactionResource::resolveRelatedPartyBankAccount(
            $transaction->related_type,
            $transaction->related_id,
        );

        if ($account) {
            return $account;
        }

        $bill = $transaction->bills->first();

        if (! $bill) {
            return null;
        }

        return $bill->branch?->bankAccounts?->first()
            ?? $bill->provider?->bankAccounts?->first()
            ?? $bill->branch?->provider?->bankAccounts?->first();
    }
}
