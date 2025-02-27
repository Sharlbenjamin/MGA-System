<?php

namespace App\Filament\Admin\Resources\ProviderBranchResource\Pages;

use App\Filament\Admin\Resources\ProviderBranchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderBranches extends ListRecords
{
    protected static string $resource = ProviderBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
