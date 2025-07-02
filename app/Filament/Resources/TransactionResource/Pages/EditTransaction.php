<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_bill')
                ->label('View Bill')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->visible(fn () => $this->record->bills()->exists())
                ->action(function () {
                    $bill = $this->record->bills()->first();
                    if ($bill) {
                        return redirect()->route('filament.admin.resources.bills.edit', $bill);
                    }
                }),
            Actions\Action::make('view_file')
                ->label('View File')
                ->icon('heroicon-o-folder')
                ->color('success')
                ->visible(fn () => $this->record->bills()->exists() && $this->record->bills()->first()->file)
                ->action(function () {
                    $bill = $this->record->bills()->first();
                    if ($bill && $bill->file) {
                        return redirect()->route('filament.admin.resources.files.edit', $bill->file);
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
