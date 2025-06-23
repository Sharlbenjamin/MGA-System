<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use App\Filament\Resources\FileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_file')
                ->label('View File')
                ->url(FileResource::getUrl('view', ['record' => $this->record->file_id]))->icon('heroicon-o-document-text'),
            Actions\Action::make('pay_bill')
                ->label('Pay Bill')
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
