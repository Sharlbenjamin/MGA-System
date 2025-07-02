<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPaidBillsWithoutTransactions extends ListRecords
{
    protected static string $resource = BillResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('status', 'Paid')
            ->whereDoesntHave('transactions');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Bills Without Trx';
    }
} 