<?php

namespace App\Filament\Admin\Resources\DrugResource\Pages;

use App\Filament\Admin\Resources\DrugResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDrug extends CreateRecord
{
    protected static string $resource = DrugResource::class;
}
