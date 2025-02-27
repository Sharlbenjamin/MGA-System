<?php

namespace App\Filament\Admin\Resources\UserSignatureResource\Pages;

use App\Filament\Admin\Resources\UserSignatureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserSignature extends EditRecord
{
    protected static string $resource = UserSignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
