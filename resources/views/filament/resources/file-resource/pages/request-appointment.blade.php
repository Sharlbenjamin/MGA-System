<x-filament-panels::page
    @class([
        'fi-resource-request-appointment-page',
        'fi-resource-record-' . $record->getKey(),
    ])
>
    {{ $this->form }}
</x-filament-panels::page>
