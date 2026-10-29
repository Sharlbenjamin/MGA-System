<?php

namespace App\Filament\Widgets;

use App\Services\FileCaseStatusOverviewService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FilesCaseStatusWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return array_map(
            fn (array $widget) => Stat::make($widget['label'], $widget['count'])
                ->description($widget['description'])
                ->descriptionIcon($widget['icon'])
                ->color($widget['color']),
            app(FileCaseStatusOverviewService::class)->widgets(),
        );
    }
}
