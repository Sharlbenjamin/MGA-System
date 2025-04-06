<?php

namespace App\Filament\Resources\FileFeeResource\Pages;

use App\Filament\Resources\FileFeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFileFee extends EditRecord
{
    protected static string $resource = FileFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
