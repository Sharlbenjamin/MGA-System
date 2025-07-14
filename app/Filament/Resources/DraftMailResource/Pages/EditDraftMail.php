<?php

namespace App\Filament\Resources\DraftMailResource\Pages;

use App\Filament\Resources\DraftMailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDraftMail extends EditRecord
{
    protected static string $resource = DraftMailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
