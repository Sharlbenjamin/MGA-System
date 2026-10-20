<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item
            :active="$coverage === 'current'"
            wire:click="$set('coverage', 'current')"
            icon="heroicon-m-check-circle"
        >
            Current coverage
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$coverage === 'missing'"
            wire:click="$set('coverage', 'missing')"
            icon="heroicon-m-exclamation-triangle"
        >
            Missing coverage
        </x-filament::tabs.item>
    </x-filament::tabs>

    <div class="mt-4">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
