<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <style>
        .fi-resource-files .fi-ta-actions-cell .fi-ta-actions {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 0.25rem !important;
        }

        .fi-resource-files .fi-ta-actions-cell .whitespace-nowrap {
            white-space: normal !important;
        }

        .fi-resource-files .fi-ta-row {
            cursor: pointer;
        }
    </style>

    <div class="flex flex-col gap-y-6">
        <x-filament-panels::resources.tabs />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
