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

        {{-- Selected File Details --}}
        @if($this->selectedFile)
        <div class="bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Selected File Details</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Details for MGA Reference: {{ $this->selectedFile->mga_reference }}</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">MGA Reference</dt>
                        <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $this->selectedFile->mga_reference }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Patient Name</dt>
                        <dd class="text-sm text-gray-950 dark:text-white">{{ $this->selectedFile->patient->name }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Client Name</dt>
                        <dd class="text-sm text-gray-950 dark:text-white">{{ $this->selectedFile->patient->client->company_name }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Service Type</dt>
                        <dd class="text-sm text-gray-950 dark:text-white">{{ $this->selectedFile->serviceType->name }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Country</dt>
                        <dd class="text-sm text-gray-950 dark:text-white">{{ $this->selectedFile->country->name ?? 'N/A' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">City</dt>
                        <dd class="text-sm text-gray-950 dark:text-white">{{ $this->selectedFile->city->name ?? 'N/A' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Date</dt>
                        <dd class="text-sm text-gray-950 dark:text-white">
                            @if($this->selectedFile->service_date)
                                {{ \Carbon\Carbon::parse($this->selectedFile->service_date)->format('F j, Y') }}
                            @else
                                Not scheduled
                            @endif
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Time</dt>
                        <dd class="text-sm text-gray-950 dark:text-white">
                            @if($this->selectedFile->service_time)
                                {{ \Carbon\Carbon::parse($this->selectedFile->service_time)->format('g:i A') }}
                            @else
                                Not scheduled
                            @endif
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Status</dt>
                        <dd>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                @switch($this->selectedFile->status)
                                    @case('New')
                                        bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                        @break
                                    @case('Handling')
                                        bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                                        @break
                                    @case('Available')
                                        bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                        @break
                                    @case('Confirmed')
                                        bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300
                                        @break
                                    @case('Assisted')
                                        bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300
                                        @break
                                    @case('Hold')
                                        bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300
                                        @break
                                    @case('Cancelled')
                                        bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                                        @break
                                    @default
                                        bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300
                                @endswitch
                            ">
                                {{ $this->selectedFile->status }}
                            </span>
                        </dd>
                    </div>
                </div>
                
                @if($this->selectedFile->address)
                <div class="mt-6 space-y-1">
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Address</dt>
                    <dd class="text-sm text-gray-950 dark:text-white">{{ $this->selectedFile->address }}</dd>
                </div>
                @endif
                
                @if($this->selectedFile->symptoms)
                <div class="mt-6 space-y-1">
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Symptoms</dt>
                    <dd class="text-sm text-gray-950 dark:text-white">{{ $this->selectedFile->symptoms }}</dd>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Information Banner --}}
        @if($this->selectedFile)
        <div class="bg-primary-50 border border-primary-200 dark:bg-primary-950 dark:border-primary-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-primary-800 dark:text-primary-200">File Selected: {{ $this->selectedFile->mga_reference }}</h3>
                    <div class="mt-2 text-sm text-primary-700 dark:text-primary-300">
                        <p>Patient: <strong>{{ $this->selectedFile->patient->name }}</strong> | Client: <strong>{{ $this->selectedFile->patient->client->company_name }}</strong></p>
                        @if($this->selectedFile->phone)
                            <p>Contact: {{ $this->selectedFile->phone }}</p>
                        @endif
                        <p class="mt-1 text-xs">Distance calculations and cost information are now available in the table below.</p>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="bg-gray-50 border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-gray-800 dark:text-gray-200">No File Selected</h3>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        <p>Select an MGA file above to view distance calculations, cost information, and enable appointment request emails.</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
        
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