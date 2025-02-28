<?php

namespace App\Filament\Admin\Resources\GopResource\Pages;

use App\Filament\Admin\Resources\GopResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGop extends EditRecord
{
    protected static string $resource = GopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
