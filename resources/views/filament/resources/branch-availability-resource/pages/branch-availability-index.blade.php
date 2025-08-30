<x-filament-panels::page>
    <div class="space-y-6">
        {{-- File Selection and Email Form --}}
        <div class="bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 rounded-xl">
            <div class="px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">File Selection & Email Configuration</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose an MGA file and configure email recipients for appointment requests</p>
            </div>
            <form wire:submit.prevent="submit" class="space-y-6 p-6">
                {{ $this->form }}
            </form>
        </div>
        
        {{-- Provider Branches Table --}}
        <div class="bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Provider Branches</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Available provider branches with contact information, services, and distance calculations.
                </p>
            </div>
            <div class="overflow-hidden">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>