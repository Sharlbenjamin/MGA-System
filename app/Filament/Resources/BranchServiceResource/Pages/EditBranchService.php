<?php

namespace App\Filament\Resources\BranchServiceResource\Pages;

use App\Filament\Resources\BranchServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranchService extends EditRecord
{
    protected static string $resource = BranchServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
