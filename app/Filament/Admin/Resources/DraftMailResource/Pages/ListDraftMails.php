<?php

namespace App\Filament\Admin\Resources\DraftMailResource\Pages;

use App\Filament\Admin\Resources\DraftMailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDraftMails extends ListRecords
{
    protected static string $resource = DraftMailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
