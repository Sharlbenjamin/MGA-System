<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewClient')
                ->label('View Client')
                ->color('success')
                ->url(function (Lead $record): ?string {
                    if ($record->client === null) {
                        return null;
                    }

                    return ClientResource::getUrl('overview', ['record' => $record->client]);
                })
                ->visible(fn (Lead $record): bool => $record->client !== null),
            Actions\DeleteAction::make(),
        ];
    }
}
