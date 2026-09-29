<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Draft only</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Downloads are marked DRAFT / مسودة and are not signed or legally approved. Have an Egyptian employment lawyer review before first use.
                Arabic and English PDFs are generated separately from the same data for this export.
            </p>
        </x-filament::section>

        <form wire:submit.prevent class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="button" wire:click="downloadArabic" icon="heroicon-o-arrow-down-tray" color="primary">
                    Download Arabic PDF
                </x-filament::button>
                <x-filament::button type="button" wire:click="downloadEnglish" icon="heroicon-o-arrow-down-tray" color="gray">
                    Download English PDF
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
