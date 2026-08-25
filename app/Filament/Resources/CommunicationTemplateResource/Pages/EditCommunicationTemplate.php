<?php

namespace App\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Filament\Resources\CommunicationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunicationTemplate extends EditRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
