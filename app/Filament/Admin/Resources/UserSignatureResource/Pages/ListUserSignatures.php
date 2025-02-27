<?php

namespace App\Filament\Admin\Resources\UserSignatureResource\Pages;

use App\Filament\Admin\Resources\UserSignatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserSignatures extends ListRecords
{
    protected static string $resource = UserSignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
