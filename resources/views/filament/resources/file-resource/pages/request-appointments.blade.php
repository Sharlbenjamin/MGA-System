<x-filament-panels::page>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Header with File Info -->
        <div class="bg-gray-50 p-4 rounded-lg border">
            <div class="grid grid-cols-4 gap-4 text-sm">
                <div class="col-span-1">
                    <span class="font-semibold text-gray-700">MGA Reference:</span>
                    <span class="ml-2">{{ $this->file->mga_reference }}</span>
                </div>
                <div class="col-span-1">
                    <span class="font-semibold text-gray-700">Patient:</span>
                    <span class="ml-2">{{ $this->file->patient->name }}</span>
                </div>
                <div class="col-span-1">
                    <span class="font-semibold text-gray-700">Service:</span>
                    <span class="ml-2">{{ $this->file->serviceType->name }}</span>
                </div>
                <div class="col-span-1">
                    <span class="font-semibold text-gray-700">Location:</span>
                    <span class="ml-2">{{ $this->file->city?->name ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white p-4 rounded-lg border shadow-sm">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Filters & Options</h3>
            <div class="grid grid-cols-4 gap-4 max-w-full">
                <!-- Search -->
                <div class="col-span-1 min-w-0">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search Branches</label>
                    <input type="text" wire:model.live="search" placeholder="Search by name, provider..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Service Type Filter -->
                <div class="col-span-1 min-w-0">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                    <select wire:model.live="serviceTypeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Services</option>
                        @foreach(\App\Models\ServiceType::all() as $serviceType)
                            <option value="{{ $serviceType->id }}" {{ $this->file->service_type_id == $serviceType->id ? 'selected' : '' }}>
                                {{ $serviceType->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Country Filter -->
                <div class="col-span-1 min-w-0">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                    <select wire:model.live="countryFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Countries</option>
                        @foreach(\App\Models\Country::all() as $country)
                            <option value="{{ $country->id }}" {{ $this->file->country_id == $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-span-1 min-w-0">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Provider Status</label>
                    <select wire:model.live="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Potential">Potential</option>
                        <option value="Hold">Hold</option>
                    </select>
                </div>
            </div>

            <!-- Additional Options -->
            <div class="mt-4 grid grid-cols-4 gap-4 max-w-full">
                <div class="col-span-1 min-w-0">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model.live="showProvinceBranches" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700 break-words">Show Province Branches</span>
                    </label>
                </div>
                
                <div class="col-span-1 min-w-0">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model.live="showOnlyWithEmail" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700 break-words">Show Only Branches with Email</span>
                    </label>
                </div>

                <div class="col-span-1 min-w-0">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model.live="showOnlyWithPhone" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700 break-words">Show Only Branches with Phone</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Custom Emails Section -->
        <div class="bg-white p-4 rounded-lg border shadow-sm">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Custom Email Addresses</h3>
            <div class="space-y-2">
                @foreach($this->customEmails as $index => $emailData)
                    <div class="flex gap-2">
                        <input type="email" wire:model="customEmails.{{ $index }}.email" placeholder="Enter custom email address" 
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <button type="button" wire:click="removeCustomEmail({{ $index }})" 
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Remove
                        </button>
                    </div>
                @endforeach
                <div class="flex gap-2">
                    <input type="email" placeholder="Enter custom email address" 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" disabled>
                    <button type="button" wire:click="addCustomEmail" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Add Email
                    </button>
                </div>
            </div>
        </div>

        <!-- Modern Filament Table -->
        {{ $this->table }}
    </div>

    <!-- Phone Info Modal -->
    @if($selectedBranchForPhone ?? false)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Contact Information</h3>
                        <button type="button" wire:click="$set('selectedBranchForPhone', null)" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    @php
                        $phoneInfo = $this->getPhoneInfo($selectedBranchForPhone);
                    @endphp
                    @if($phoneInfo)
                        <div class="space-y-3">
                            <div class="font-medium text-gray-900">{{ $phoneInfo['branch_name'] }}</div>
                            <div class="text-sm text-gray-600 space-y-2">
                                @if($phoneInfo['direct_phone'])
                                    <div><strong>Direct Phone:</strong> {{ $phoneInfo['direct_phone'] }}</div>
                                @endif
                                @if($phoneInfo['operation_contact']['name'])
                                    <div><strong>Operation Contact:</strong> {{ $phoneInfo['operation_contact']['name'] }}</div>
                                    @if($phoneInfo['operation_contact']['phone'])
                                        <div class="ml-4">Phone: {{ $phoneInfo['operation_contact']['phone'] }}</div>
                                    @endif
                                    @if($phoneInfo['operation_contact']['email'])
                                        <div class="ml-4">Email: {{ $phoneInfo['operation_contact']['email'] }}</div>
                                    @endif
                                @endif
                                @if($phoneInfo['gop_contact']['name'])
                                    <div><strong>GOP Contact:</strong> {{ $phoneInfo['gop_contact']['name'] }}</div>
                                    @if($phoneInfo['gop_contact']['phone'])
                                        <div class="ml-4">Phone: {{ $phoneInfo['gop_contact']['phone'] }}</div>
                                    @endif
                                    @if($phoneInfo['gop_contact']['email'])
                                        <div class="ml-4">Email: {{ $phoneInfo['gop_contact']['email'] }}</div>
                                    @endif
                                @endif
                                @if($phoneInfo['financial_contact']['name'])
                                    <div><strong>Financial Contact:</strong> {{ $phoneInfo['financial_contact']['name'] }}</div>
                                    @if($phoneInfo['financial_contact']['phone'])
                                        <div class="ml-4">Phone: {{ $phoneInfo['financial_contact']['phone'] }}</div>
                                    @endif
                                    @if($phoneInfo['financial_contact']['email'])
                                        <div class="ml-4">Email: {{ $phoneInfo['financial_contact']['email'] }}</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-gray-500">Contact information not available.</div>
                    @endif
                    <div class="mt-6 flex justify-end">
                        <button type="button" wire:click="$set('selectedBranchForPhone', null)" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
