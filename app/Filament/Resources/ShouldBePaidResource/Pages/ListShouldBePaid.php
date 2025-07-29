<?php

namespace App\Filament\Resources\ShouldBePaidResource\Pages;

use App\Filament\Resources\ShouldBePaidResource;
use App\Filament\Widgets\UnpaidBillsSummary;
use App\Filament\Widgets\ProvidersNeedPaymentWidget;
use App\Filament\Widgets\TotalUnpaidWidget;
use App\Filament\Widgets\UnpaidBillsWidget;
use App\Filament\Widgets\AmountUnpaidWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShouldBePaid extends ListRecords
{
    protected static string $resource = ShouldBePaidResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            UnpaidBillsSummary::class,
            ProvidersNeedPaymentWidget::class,
            TotalUnpaidWidget::class,
            UnpaidBillsWidget::class,
            AmountUnpaidWidget::class,
        ];
    }

    public function getTitle(): string
    {
        $count = $this->getTableQuery()->count();
        return "Unpaid Bills ({$count})";
    }
} 