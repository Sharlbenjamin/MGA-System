<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use App\Filament\Resources\FileResource;
use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Debug: Log the data being saved
        \Log::info('Bill edit save data:', $data);
        
        // Ensure bank_account_id is properly handled
        if (isset($data['bank_account_id']) && ($data['bank_account_id'] === 0 || $data['bank_account_id'] === '0')) {
            $data['bank_account_id'] = null;
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Debug: Log successful save
        \Log::info('Bill saved successfully', ['bill_id' => $this->record->id]);
        
        Notification::make()
            ->success()
            ->title('Bill updated successfully')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_file')
                ->label('View File')
                ->url(FileResource::getUrl('view', ['record' => $this->record->file_id]))->icon('heroicon-o-document-text'),
            Actions\Action::make('view_transaction')
                ->label('View Transaction')
                ->icon('heroicon-o-rectangle-stack')
                ->color('primary')
                ->visible(fn () => $this->record->transactions()->exists())
                ->action(function () {
                    $transaction = $this->record->transactions()->first();
                    if ($transaction) {
                        return redirect()->route('filament.admin.resources.transactions.edit', $transaction);
                    }
                }),
            Actions\Action::make('pay_bill')
                ->label('Pay Bill')
                ->color('success')
                ->hidden(fn ($record) => $record->status === 'Paid')
                ->url(function () {
                    $bill = $this->record;
                    $params = [
                        'type' => 'Outflow',
                        'amount' => $bill->total_amount,
                        'name' => 'Payment for ' . $bill->name. ' on ' . now()->format('d/m/Y'),
                        'date' => now()->format('Y-m-d'),
                        'bill_id' => $bill->id,
                    ];
                    
                    // Determine related type and ID based on bill relationships
                    if ($bill->branch_id) {
                        $params['related_type'] = 'Branch';
                        $params['related_id'] = $bill->branch_id;
                    } elseif ($bill->provider_id) {
                        $params['related_type'] = 'Provider';
                        $params['related_id'] = $bill->provider_id;
                    }
                    
                    // Add bank account if available
                    if ($bill->bank_account_id) {
                        $params['bank_account_id'] = $bill->bank_account_id;
                    }
                    
                    return TransactionResource::getUrl('create', $params);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
