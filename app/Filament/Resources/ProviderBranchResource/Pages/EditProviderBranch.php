<?php

namespace App\Filament\Resources\ProviderBranchResource\Pages;

use App\Filament\Resources\ProviderBranchResource;
use App\Models\ProviderBranch;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProviderBranch extends EditRecord
{
    protected static string $resource = ProviderBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Overview')
                ->url(fn (ProviderBranch $record) => ProviderBranchResource::getUrl('overview', ['record' => $record]))->color('success'),
            Actions\DeleteAction::make(),
        ];
    }
}
