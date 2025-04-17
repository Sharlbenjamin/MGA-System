<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Overview')
                ->url(fn (Client $record) => ClientResource::getUrl('overview', ['record' => $record]))->color('success'),
            Actions\DeleteAction::make(),
        ];
    }
}
