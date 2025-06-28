<?php

namespace App\Filament\Resources\DatabaseNotificationResource\Pages;

use App\Filament\Resources\DatabaseNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDatabaseNotification extends EditRecord
{
    protected static string $resource = DatabaseNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
