<div class="space-y-6">
    <!-- Header with File Info -->
    <div class="bg-gray-50 p-4 rounded-lg border">
        <div class="grid grid-cols-4 gap-4 text-sm">
            <div>
                <span class="font-semibold text-gray-700">MGA Reference:</span>
                <span class="ml-2">{{ $file->mga_reference }}</span>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Patient:</span>
                <span class="ml-2">{{ $file->patient->name }}</span>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Service:</span>
                <span class="ml-2">{{ $file->serviceType->name }}</span>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Location:</span>
                <span class="ml-2">{{ $file->city?->name ?? 'N/A' }}</span>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white p-4 rounded-lg border shadow-sm">
        <h3 class="text-lg font-semibold mb-4 text-gray-800">Filters & Options</h3>
        <div class="grid grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Branches</label>
                <input type="text" wire:model.live="search" placeholder="Search by name, provider..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Service Type Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                <select wire:model.live="serviceTypeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Services</option>
                    @foreach($serviceTypes as $serviceType)
                        <option value="{{ $serviceType->id }}" {{ $file->service_type_id == $serviceType->id ? 'selected' : '' }}>
                            {{ $serviceType->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Country Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <select wire:model.live="countryFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Countries</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" {{ $file->country_id == $country->id ? 'selected' : '' }}>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
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
        <div class="mt-4 flex flex-wrap gap-4 items-center">
            <label class="flex items-center">
                <input type="checkbox" wire:model.live="showProvinceBranches" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">Show Province Branches</span>
            </label>
            
            <label class="flex items-center">
                <input type="checkbox" wire:model.live="showOnlyWithEmail" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">Show Only Branches with Email</span>
            </label>

            <label class="flex items-center">
                <input type="checkbox" wire:model.live="showOnlyWithPhone" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">Show Only Branches with Phone</span>
            </label>
        </div>
    </div>

    <!-- Custom Emails Section -->
    <div class="bg-white p-4 rounded-lg border shadow-sm">
        <h3 class="text-lg font-semibold mb-4 text-gray-800">Custom Email Addresses</h3>
        <div class="space-y-2">
            @foreach($customEmails as $index => $email)
                <div class="flex gap-2">
                    <input type="email" wire:model="customEmails.{{ $index }}" placeholder="Enter custom email address" 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @if($index > 0)
                        <button type="button" wire:click="removeCustomEmail({{ $index }})" 
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Remove
                        </button>
                    @endif
                </div>
            @endforeach
            <button type="button" wire:click="addCustomEmail" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Add Email
            </button>
        </div>
    </div>

    <!-- Branches Table -->
    <div class="bg-white rounded-lg border shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Available Provider Branches</h3>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">
                        {{ count($selectedBranches) }} of {{ $branches->total() }} selected
                    </span>
                    <button type="button" wire:click="selectAll" 
                            class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        Select All
                    </button>
                    <button type="button" wire:click="clearSelection" 
                            class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                        Clear All
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" wire:click="selectAll" 
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" 
                            wire:click="sortBy('branch_name')">
                            Branch Name 
                            @if($sortField === 'branch_name')
                                {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                            @else
                                ↕
                            @endif
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" 
                            wire:click="sortBy('provider_name')">
                            Provider 
                            @if($sortField === 'provider_name')
                                {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                            @else
                                ↕
                            @endif
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" 
                            wire:click="sortBy('priority')">
                            Priority 
                            @if($sortField === 'priority')
                                {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                            @else
                                ↕
                            @endif
                        </th>

                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            City
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cost
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Distance
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Contact Info
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($branches as $branch)
                        @php
                            $hasEmail = $this->hasEmail($branch);
                            $hasPhone = $this->hasPhone($branch);
                            $distance = $this->getDistanceToBranch($branch);
                            $cost = $this->getCostForService($branch);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input type="checkbox" wire:click="toggleBranch({{ $branch->id }})" 
                                       @if(in_array($branch->id, $selectedBranches)) checked @endif
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">
                                    <a href="{{ route('filament.admin.resources.provider-branches.overview', $branch) }}" 
                                       target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $branch->branch_name }}
                                    </a>
                                </div>
                                @if($branch->all_country)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        All Country
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $branch->provider?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $branch->priority ?? 'N/A' }}</td>

                            <td class="px-4 py-3 text-sm text-gray-900">
                                @if($branch->cities && $branch->cities->count() > 0)
                                    {{ $branch->cities->pluck('name')->implode(', ') }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                @if($cost)
                                    €{{ number_format($cost, 2) }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $distance }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-2">
                                    @if($hasEmail)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                            </svg>
                                            Email
                                        </span>
                                    @endif
                                    @if($hasPhone)
                                        <button type="button" wire:click="$set('selectedBranchForPhone', {{ $branch->id }})" 
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                            </svg>
                                            Phone
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                    {{ ($branch->provider?->status ?? '') === 'Active' ? 'bg-green-100 text-green-800' : 
                                       (($branch->provider?->status ?? '') === 'Potential' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $branch->provider?->status ?? 'N/A' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                No branches found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($branches->hasPages())
            <div class="px-4 py-3 border-t bg-gray-50">
                {{ $branches->links() }}
            </div>
        @endif
    </div>

    <!-- Phone Info Modal -->
    @if($selectedBranchForPhone)
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
</div>


