<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\FileResource;
use Filament\Actions;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('file')
                ->label('View File')
                ->url(FileResource::getUrl('view', ['record' => $this->record->file_id]))
                ->icon('heroicon-o-document-text')->color('primary'),

                Actions\Action::make('transaction')
                ->label('Invoice Paid')
                ->color('success')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->title('Payment processing')
                        ->body('This functionality is not yet implemented.')
                        ->info()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}