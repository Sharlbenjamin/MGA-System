<?php

namespace App\Filament\Resources\ShouldBePaidResource\Pages;

use App\Filament\Resources\ShouldBePaidResource;
use App\Filament\Widgets\UnpaidBillsSummary;
use App\Filament\Widgets\UnpaidBillsOverdueWidget;
use App\Filament\Widgets\UnpaidBillsMissingDocumentsWidget;
use App\Filament\Widgets\UnpaidBillsWithPaidInvoicesWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShouldBePaid extends ListRecords
{
    protected static string $resource = ShouldBePaidResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            UnpaidBillsSummary::class,
            UnpaidBillsOverdueWidget::class,
            UnpaidBillsMissingDocumentsWidget::class,
            UnpaidBillsWithPaidInvoicesWidget::class,
        ];
    }

    public function getTitle(): string
    {
        $count = $this->getTableQuery()->count();
        return "Unpaid Bills ({$count})";
    }
} 