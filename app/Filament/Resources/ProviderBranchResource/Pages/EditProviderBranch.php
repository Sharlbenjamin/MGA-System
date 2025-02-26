<?php

namespace App\Filament\Resources\ProviderBranchResource\Pages;

use App\Filament\Resources\ProviderBranchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProviderBranch extends EditRecord
{
    protected static string $resource = ProviderBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
