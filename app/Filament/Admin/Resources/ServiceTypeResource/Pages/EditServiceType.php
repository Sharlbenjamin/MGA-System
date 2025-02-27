<?php

namespace App\Filament\Admin\Resources\ServiceTypeResource\Pages;

use App\Filament\Admin\Resources\ServiceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceType extends EditRecord
{
    protected static string $resource = ServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
