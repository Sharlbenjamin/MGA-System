<?php

namespace App\Filament\Resources\BillWithoutTransactionResource\Pages;

use App\Filament\Resources\BillWithoutTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBillWithoutTransaction extends EditRecord
{
    protected static string $resource = BillWithoutTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
} 