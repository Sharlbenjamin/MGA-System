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
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProvidersNeedPaymentWidget::class,
            TotalUnpaidWidget::class,
            UnpaidBillsWidget::class,
            AmountUnpaidWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int | string | array
    {
        return 4;
    }

    public function getTitle(): string
    {
        $count = $this->getTableQuery()->count();
        return "Unpaid Bills ({$count})";
    }
} 