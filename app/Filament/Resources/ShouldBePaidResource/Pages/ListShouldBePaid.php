<?php

namespace App\Filament\Resources\ShouldBePaidResource\Pages;

use App\Filament\Resources\ShouldBePaidResource;
use App\Filament\Widgets\UnpaidBillsSummary;
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

    public function getTitle(): string
    {
        $count = $this->getTableQuery()->count();
        return "Unpaid Bills ({$count})";
    }
} 