<?php

namespace App\Filament\Resources\FileFeeResource\Pages;

use App\Filament\Resources\FileFeeResource;
use App\Filament\Resources\FileFeeResource\Pages\Concerns\NormalizesFileFeeFormData;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFileFee extends EditRecord
{
    use NormalizesFileFeeFormData;

    protected static string $resource = FileFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->validateTierPackageHasAmount();

        return $this->normalizeFileFeeData($data);
    }
}
