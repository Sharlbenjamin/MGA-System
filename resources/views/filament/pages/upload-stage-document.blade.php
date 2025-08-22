<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Upload Form Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Upload Stage Document</h2>
            
            <form wire:submit="submit">
                {{ $this->form }}
                
                <div class="mt-6">
                    <x-filament::button type="submit">
                        Upload Document
                    </x-filament::button>
                </div>
            </form>
        </div>

        <!-- Files Without Documents Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Files Without Documents</h3>
                <p class="text-sm text-gray-600 mt-1">Files that don't have documents uploaded yet</p>
            </div>
            
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
