<?php

namespace App\Filament\Resources\FileFeeResource\Pages;

use App\Filament\Resources\FileFeeResource;
use App\Filament\Resources\FileFeeResource\Pages\Concerns\NormalizesFileFeeFormData;
use Filament\Resources\Pages\CreateRecord;

class CreateFileFee extends CreateRecord
{
    use NormalizesFileFeeFormData;

    protected static string $resource = FileFeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->validateTierPackageHasAmount();

        return $this->normalizeFileFeeData($data);
    }
}
