<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\Page;

class ImportTransactions extends Page
{
    protected static string $resource = TransactionResource::class;

    protected static string $view = 'filament.resources.transaction-resource.pages.import-transactions';

    protected static ?string $title = 'Import bank transactions';

    public function mount(): void
    {
        $this->redirect(BankAccountResource::getUrl('index'));
    }
}
