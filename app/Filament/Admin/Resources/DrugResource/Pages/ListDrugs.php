<?php

namespace App\Filament\Admin\Resources\DrugResource\Pages;

use App\Filament\Admin\Resources\DrugResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDrugs extends ListRecords
{
    protected static string $resource = DrugResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
