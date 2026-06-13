<x-filament-panels::page>
    <form wire:submit.prevent="confirmImport">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button type="button" wire:click="parseUpload" color="gray">
                Parse file
            </x-filament::button>
            <x-filament::button type="button" wire:click="confirmImport" color="success">
                Confirm import
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
