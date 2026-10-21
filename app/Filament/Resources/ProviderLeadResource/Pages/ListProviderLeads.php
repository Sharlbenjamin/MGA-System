<?php

namespace App\Filament\Resources\ProviderLeadResource\Pages;

use App\Filament\Resources\ProviderLeadResource;
use App\Filament\Resources\ProviderLeadResource\Widgets\MonthlyInteractionsByCountryWidget;
use App\Filament\Resources\ProviderLeadResource\Widgets\ProviderLeadsOverviewWidget;
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

                    .fi-resource-provider-leads .fi-ta-header-cell,
                    .fi-resource-provider-leads .fi-ta-cell:not(.fi-ta-selection-cell):not(.fi-ta-actions-cell) {
                        white-space: normal !important;
                        overflow-wrap: anywhere;
                    }

                    .fi-resource-provider-leads .fi-ta-selection-cell {
                        width: 3.25rem !important;
                        min-width: 3.25rem !important;
                        white-space: nowrap !important;
                    }

                    .fi-resource-provider-leads .fi-ta-actions-header-cell,
                    .fi-resource-provider-leads .fi-ta-actions-cell {
                        width: 22rem !important;
                        min-width: 22rem !important;
                        white-space: nowrap !important;
                    }

                    .fi-resource-provider-leads .fi-ta-actions-cell .whitespace-nowrap {
                        white-space: nowrap !important;
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
            ProviderLeadsOverviewWidget::class,
            MonthlyInteractionsByCountryWidget::class,
        ];
    }
}
