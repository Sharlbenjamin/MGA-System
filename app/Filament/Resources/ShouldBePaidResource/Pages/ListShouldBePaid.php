<?php

namespace App\Filament\Resources\ShouldBePaidResource\Pages;

use App\Filament\Resources\ShouldBePaidResource;
use App\Filament\Widgets\UnpaidBillsSummary;
use App\Filament\Widgets\UnpaidBillsByBankAccount;
use App\Filament\Widgets\UnpaidBillsOverdue;
use App\Filament\Widgets\UnpaidBillsMissingDocuments;
use App\Filament\Widgets\UnpaidBillsWithPaidInvoices;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShouldBePaid extends ListRecords
{
    protected static string $resource = ShouldBePaidResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            UnpaidBillsSummary::class,
            UnpaidBillsByBankAccount::class,
            UnpaidBillsOverdue::class,
            UnpaidBillsMissingDocuments::class,
            UnpaidBillsWithPaidInvoices::class,
        ];
    }

    public function getTitle(): string
    {
        $count = $this->getTableQuery()->count();
        return "Unpaid Bills ({$count})";
    }
} 