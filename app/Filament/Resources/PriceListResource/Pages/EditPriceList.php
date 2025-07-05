<?php

namespace App\Filament\Resources\PriceListResource\Pages;

use App\Filament\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    // Clear cache after deletion
                    PriceListResource::clearCache();
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Clear cache to refresh the tabbed view
        PriceListResource::clearCache();
    }
}
