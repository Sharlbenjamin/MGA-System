@php
    $file = $file();
    $branchesData = $branches();
    $allBranches = $branchesData['allBranches'];
    $cityBranches = $branchesData['cityBranches'];
@endphp

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
                <input type="text" id="branchSearch" placeholder="Search by name, provider..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Service Type Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                <select id="serviceTypeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Services</option>
                    @foreach(\App\Models\ServiceType::all() as $serviceType)
                        <option value="{{ $serviceType->id }}" {{ $file->service_type_id == $serviceType->id ? 'selected' : '' }}>
                            {{ $serviceType->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Country Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <select id="countryFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Countries</option>
                    @foreach(\App\Models\Country::all() as $country)
                        <option value="{{ $country->id }}" {{ $file->country_id == $country->id ? 'selected' : '' }}>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Provider Status</label>
                <select id="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                <input type="checkbox" id="showProvinceBranches" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">Show Province Branches</span>
            </label>
            
            <label class="flex items-center">
                <input type="checkbox" id="showOnlyWithEmail" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">Show Only Branches with Email</span>
            </label>

            <label class="flex items-center">
                <input type="checkbox" id="showOnlyWithPhone" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">Show Only Branches with Phone</span>
            </label>
        </div>
    </div>

    <!-- Custom Emails Section -->
    <div class="bg-white p-4 rounded-lg border shadow-sm">
        <h3 class="text-lg font-semibold mb-4 text-gray-800">Custom Email Addresses</h3>
        <div id="customEmailsContainer" class="space-y-2">
            <div class="flex gap-2">
                <input type="email" placeholder="Enter custom email address" 
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="button" onclick="addCustomEmail()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Add Email
                </button>
            </div>
        </div>
    </div>

    <!-- Branches Table -->
    <div class="bg-white rounded-lg border shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Available Provider Branches</h3>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">
                        <span id="selectedCount">0</span> of <span id="totalCount">{{ $allBranches->count() }}</span> selected
                    </span>
                    <button type="button" onclick="selectAll()" 
                            class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        Select All
                    </button>
                    <button type="button" onclick="clearSelection()" 
                            class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                        Clear All
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full" id="branchesTable">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(1)">
                            Branch Name ↕
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(2)">
                            Provider ↕
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(3)">
                            Priority ↕
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(4)">
                            Country ↕
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(5)">
                            City ↕
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
                <tbody class="bg-white divide-y divide-gray-200" id="branchesTableBody">
                    @foreach($allBranches as $branch)
                        @php
                            $hasEmail = $branch->email || $branch->operationContact?->email;
                            $hasPhone = $branch->phone || $branch->operationContact?->phone_number;
                            $distance = $file->getDistanceToBranch($branch);
                            $cost = $branch->getCostForService($file->service_type_id);
                        @endphp
                        <tr class="branch-row hover:bg-gray-50" 
                            data-branch-id="{{ $branch->id }}"
                            data-branch-name="{{ strtolower($branch->branch_name ?? '') }}"
                            data-provider-name="{{ strtolower($branch->provider?->name ?? '') }}"
                            data-service-type="{{ $branch->branchServices->where('service_type_id', $file->service_type_id)->first()?->service_type_id }}"
                            data-country="{{ strtolower($branch->provider?->country?->name ?? '') }}"
                            data-status="{{ strtolower($branch->provider?->status ?? '') }}"
                            data-has-email="{{ $hasEmail ? 'true' : 'false' }}"
                            data-has-phone="{{ $hasPhone ? 'true' : 'false' }}"
                            data-priority="{{ $branch->priority ?? '0' }}">
                            <td class="px-4 py-3">
                                <input type="checkbox" name="selected_branches[]" value="{{ $branch->id }}" 
                                       class="branch-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
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
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $branch->provider?->country?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $branch->cities ? $branch->cities->pluck('name')->implode(', ') : 'N/A' }}
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
                                        <button type="button" onclick="showPhoneInfo({{ $branch->id }})" 
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Phone Info Modal -->
    <div id="phoneModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Contact Information</h3>
                    <button type="button" onclick="hidePhoneModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="phoneModalContent" class="space-y-3">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="hidePhoneModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentSortColumn = -1;
let currentSortDirection = 'asc';

// Initialize the table
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
    setupEventListeners();
});

function setupEventListeners() {
    // Search functionality
    document.getElementById('branchSearch').addEventListener('input', filterBranches);
    
    // Filter functionality
    document.getElementById('serviceTypeFilter').addEventListener('change', filterBranches);
    document.getElementById('countryFilter').addEventListener('change', filterBranches);
    document.getElementById('statusFilter').addEventListener('change', filterBranches);
    
    // Checkbox functionality
    document.getElementById('selectAllCheckbox').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.branch-checkbox:not([style*="display: none"])');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Individual checkbox functionality
    document.querySelectorAll('.branch-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Additional filters
    document.getElementById('showProvinceBranches').addEventListener('change', filterBranches);
    document.getElementById('showOnlyWithEmail').addEventListener('change', filterBranches);
    document.getElementById('showOnlyWithPhone').addEventListener('change', filterBranches);
}

function filterBranches() {
    const searchTerm = document.getElementById('branchSearch').value.toLowerCase();
    const serviceType = document.getElementById('serviceTypeFilter').value;
    const country = document.getElementById('countryFilter').value;
    const status = document.getElementById('statusFilter').value;
    const showProvince = document.getElementById('showProvinceBranches').checked;
    const showOnlyEmail = document.getElementById('showOnlyWithEmail').checked;
    const showOnlyPhone = document.getElementById('showOnlyWithPhone').checked;
    
    const rows = document.querySelectorAll('.branch-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        let show = true;
        
        // Search filter
        if (searchTerm) {
            const branchName = row.dataset.branchName;
            const providerName = row.dataset.providerName;
            if (!branchName.includes(searchTerm) && !providerName.includes(searchTerm)) {
                show = false;
            }
        }
        
        // Service type filter
        if (serviceType && row.dataset.serviceType !== serviceType) {
            show = false;
        }
        
        // Country filter
        if (country && row.dataset.country !== country.toLowerCase()) {
            show = false;
        }
        
        // Status filter
        if (status && row.dataset.status !== status.toLowerCase()) {
            show = false;
        }
        
        // Email filter
        if (showOnlyEmail && row.dataset.hasEmail !== 'true') {
            show = false;
        }
        
        // Phone filter
        if (showOnlyPhone && row.dataset.hasPhone !== 'true') {
            show = false;
        }
        
        if (show) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('totalCount').textContent = visibleCount;
    updateSelectedCount();
}

function sortTable(columnIndex) {
    const table = document.getElementById('branchesTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    if (currentSortColumn === columnIndex) {
        currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortColumn = columnIndex;
        currentSortDirection = 'asc';
    }
    
    rows.sort((a, b) => {
        let aValue, bValue;
        
        switch(columnIndex) {
            case 1: // Branch Name
                aValue = a.dataset.branchName;
                bValue = b.dataset.branchName;
                break;
            case 2: // Provider
                aValue = a.dataset.providerName;
                bValue = b.dataset.providerName;
                break;
            case 3: // Priority
                aValue = parseInt(a.dataset.priority);
                bValue = parseInt(b.dataset.priority);
                break;
            case 4: // Country
                aValue = a.dataset.country;
                bValue = b.dataset.country;
                break;
            case 5: // City
                aValue = a.cells[5].textContent.trim().toLowerCase();
                bValue = b.cells[5].textContent.trim().toLowerCase();
                break;
            default:
                return 0;
        }
        
        if (currentSortDirection === 'asc') {
            return aValue > bValue ? 1 : -1;
        } else {
            return aValue < bValue ? 1 : -1;
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    const headers = table.querySelectorAll('th');
    headers.forEach((header, index) => {
        if (index === columnIndex) {
            header.textContent = header.textContent.replace(' ↕', '') + 
                (currentSortDirection === 'asc' ? ' ↑' : ' ↓');
        } else if (index > 0) {
            header.textContent = header.textContent.replace(/[↑↓]/, '') + ' ↕';
        }
    });
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.branch-checkbox:not([style*="display: none"])');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.branch-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function updateSelectedCount() {
    const selectedCheckboxes = document.querySelectorAll('.branch-checkbox:checked:not([style*="display: none"])');
    const totalVisible = document.querySelectorAll('.branch-row:not([style*="display: none"])').length;
    document.getElementById('selectedCount').textContent = selectedCheckboxes.length;
    document.getElementById('totalCount').textContent = totalVisible;
}

function addCustomEmail() {
    const container = document.getElementById('customEmailsContainer');
    const newEmailDiv = document.createElement('div');
    newEmailDiv.className = 'flex gap-2';
    newEmailDiv.innerHTML = `
        <input type="email" name="custom_emails[]" placeholder="Enter custom email address" 
               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <button type="button" onclick="removeCustomEmail(this)" 
                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
            Remove
        </button>
    `;
    container.appendChild(newEmailDiv);
}

function removeCustomEmail(button) {
    button.parentElement.remove();
}

function showPhoneInfo(branchId) {
    // This would typically fetch the phone info via AJAX
    // For now, we'll show a placeholder
    const modal = document.getElementById('phoneModal');
    const content = document.getElementById('phoneModalContent');
    
    // Find the branch data from the table
    const row = document.querySelector('[data-branch-id="' + branchId + '"]');
    const branchName = row.querySelector('td:nth-child(2)').textContent.trim();
    
    content.innerHTML = '<div class="font-medium text-gray-900">' + branchName + '</div>' +
        '<div class="text-sm text-gray-600">' +
        '<div><strong>Direct Phone:</strong> ' + (row.querySelector('td:nth-child(9)').textContent.includes('Phone') ? 'Available' : 'N/A') + '</div>' +
        '<div><strong>Operation Contact:</strong> Available</div>' +
        '<div><strong>GOP Contact:</strong> Available</div>' +
        '</div>';
    
    modal.classList.remove('hidden');
}

function hidePhoneModal() {
    document.getElementById('phoneModal').classList.add('hidden');
}

// Handle form submission
document.addEventListener('submit', function(e) {
    const selectedBranches = Array.from(document.querySelectorAll('.branch-checkbox:checked'))
        .map(checkbox => checkbox.value);
    
    const customEmails = Array.from(document.querySelectorAll('input[name="custom_emails[]"]'))
        .map(input => input.value)
        .filter(email => email.trim() !== '');
    
    if (selectedBranches.length === 0 && customEmails.length === 0) {
        e.preventDefault();
        alert('Please select at least one provider branch or add a custom email address.');
        return false;
    }
});
</script>
