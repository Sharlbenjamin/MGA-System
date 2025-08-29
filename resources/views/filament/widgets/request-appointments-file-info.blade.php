<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="text-sm font-medium text-gray-500">Patient Name</div>
                <div class="text-lg font-semibold text-red-600">{{ $file->patient->name ?? 'N/A' }}</div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="text-sm font-medium text-gray-500">MGA Reference</div>
                <div class="text-lg font-semibold text-yellow-600">{{ $file->mga_reference ?? 'N/A' }}</div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="text-sm font-medium text-gray-500">Service Type</div>
                <div class="text-lg font-semibold text-green-600">{{ $file->serviceType->name ?? 'N/A' }}</div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="text-sm font-medium text-gray-500">Location</div>
                <div class="text-lg font-semibold text-blue-600">
                    {{ $file->city?->name ?? 'N/A' }}, {{ $file->country?->name ?? 'N/A' }}
                </div>
            </div>
        </div>
        
        @if($file->address)
        <div class="mt-4 bg-white rounded-lg p-4 shadow-sm border">
            <div class="text-sm font-medium text-gray-500">Address</div>
            <div class="text-base text-gray-700">{{ $file->address }}</div>
        </div>
        @endif
        
        @if($file->symptoms)
        <div class="mt-4 bg-white rounded-lg p-4 shadow-sm border">
            <div class="text-sm font-medium text-gray-500">Symptoms</div>
            <div class="text-base text-gray-700">{{ $file->symptoms }}</div>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
