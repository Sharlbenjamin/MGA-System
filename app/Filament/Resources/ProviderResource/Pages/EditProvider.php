<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use App\Models\Provider;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Overview')
            ->url(fn (Provider $record) => ProviderResource::getUrl('overview', ['record' => $record]))->color('success'),
            Actions\DeleteAction::make(),
        ];
    }
}
