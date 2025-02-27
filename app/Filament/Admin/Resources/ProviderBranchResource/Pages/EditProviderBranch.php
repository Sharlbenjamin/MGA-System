<?php

namespace App\Filament\Admin\Resources\ProviderBranchResource\Pages;

use App\Filament\Admin\Resources\ProviderBranchResource;
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
