<?php

namespace App\Filament\Resources\ProviderLeadResource\Pages;

use App\Filament\Resources\ProviderLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProviderLead extends EditRecord
{
    protected static string $resource = ProviderLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
