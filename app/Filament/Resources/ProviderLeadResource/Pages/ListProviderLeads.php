<?php

namespace App\Filament\Resources\ProviderLeadResource\Pages;

use App\Filament\Resources\ProviderLeadResource;
use App\Filament\Resources\ProviderLeadResource\Widgets\PotentialProviderLeadsByCountryWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class ListProviderLeads extends ListRecords
{
    protected static string $resource = ProviderLeadResource::class;

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
            fn (): string => <<<'HTML'
                <style>
                    .fi-resource-provider-leads table.fi-ta-table {
                        width: max-content !important;
                    }

                    .fi-resource-provider-leads .fi-ta-text .w-max,
                    .fi-resource-provider-leads .fi-ta-text .max-w-max {
                        width: auto;
                        max-width: 100%;
                        white-space: normal;
                    }

                    .fi-resource-provider-leads .fi-badge {
                        height: auto;
                        white-space: normal;
                        text-align: start;
                    }
                </style>
            HTML,
            scopes: static::class,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PotentialProviderLeadsByCountryWidget::class,
        ];
    }
}
