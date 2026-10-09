<?php

namespace App\Filament\Resources\TransactionResource\Pages;

class ListAllTransactions extends ListTransactions
{
    protected static ?string $title = 'All Transactions';

    protected static ?string $navigationLabel = 'All Transactions';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    public function mount(?int $bankAccountId = null): void
    {
        $this->authorizeAccess();

        $this->loadDefaultActiveTab();
    }

    public function getTitle(): string
    {
        return 'All Transactions';
    }
}
