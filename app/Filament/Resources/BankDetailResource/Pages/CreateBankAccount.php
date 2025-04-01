<?php

namespace App\Filament\Resources\BankDetailResource\Pages;

use App\Filament\Resources\BankAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;
}