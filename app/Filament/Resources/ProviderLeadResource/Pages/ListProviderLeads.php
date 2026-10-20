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
                        width: 100% !important;
                        max-width: 100%;
                        table-layout: fixed;
                    }

                    .fi-resource-provider-leads .fi-ta-table :is(th, td),
                    .fi-resource-provider-leads .fi-ta-table .whitespace-nowrap {
                        white-space: normal !important;
                        overflow-wrap: anywhere;
                    }

                    .fi-resource-provider-leads .fi-ta-text .w-max,
                    .fi-resource-provider-leads .fi-ta-text .max-w-max {
                        width: auto;
                        max-width: 100%;
                    }

                    .fi-resource-provider-leads .fi-badge {
                        height: auto;
                        max-width: 100%;
                        text-align: start;
                    }

                    .fi-resource-provider-leads .fi-ta-actions-header-cell,
                    .fi-resource-provider-leads .fi-ta-actions-cell {
                        width: 8.5rem;
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
