<?php

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Resources\CityResource;
use App\Models\City;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateCity extends CreateRecord
{
    protected static string $resource = CityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $duplicate = City::findDuplicate(
            $data['name'],
            $data['country_id'] ?? null,
            $data['province_id'] ?? null,
        );

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => "A city matching \"{$duplicate->name}\" already exists. Check spelling, accents, or apostrophes.",
            ]);
        }

        return $data;
    }
}
