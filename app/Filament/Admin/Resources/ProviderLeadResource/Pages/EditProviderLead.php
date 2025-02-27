<?php

namespace App\Filament\Admin\Resources\ProviderLeadResource\Pages;

use App\Filament\Admin\Resources\ProviderLeadResource;
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
