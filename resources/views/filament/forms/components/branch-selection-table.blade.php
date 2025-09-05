@php
    $branches = $getState()['branches'] ?? collect();
    $record = $getState()['record'] ?? null;
@endphp

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <input 
                type="checkbox" 
                id="select-all-branches" 
                class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50"
                onchange="toggleAllBranches(this)"
            >
            <label for="select-all-branches" class="text-sm font-medium text-gray-700">
                Select All Branches
            </label>
        </div>
        <div class="text-sm text-gray-500">
            {{ $branches->count() }} branches available
        </div>
    </div>

    @if($branches->count() > 0)
        <div class="overflow-hidden border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Select
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Branch Name
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Provider
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            City
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Priority
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cost
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Contact
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($branches as $branch)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4 whitespace-nowrap">
                                <input 
                                    type="checkbox" 
                                    name="selected_branches[]" 
                                    value="{{ $branch->id }}"
                                    class="branch-checkbox rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50"
                                    onchange="updateSelectAllState()"
                                >
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $branch->branch_name }}
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $branch->provider->name ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $branch->city->name ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($branch->priority <= 3) bg-green-100 text-green-800
                                    @elseif($branch->priority <= 6) bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800
                                    @endif">
                                    {{ $branch->priority ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    @if($record && $record->service_type_id)
                                        @php
                                            $service = $branch->branchServices()
                                                ->where('service_type_id', $record->service_type_id)
                                                ->where('is_active', true)
                                                ->first();
                                        @endphp
                                        @if($service)
                                            @php
                                                $costs = array_filter([
                                                    $service->day_cost,
                                                    $service->night_cost,
                                                    $service->weekend_cost,
                                                    $service->weekend_night_cost
                                                ], function($cost) {
                                                    return $cost !== null && $cost > 0;
                                                });
                                            @endphp
                                            @if(!empty($costs))
                                                <span class="font-medium text-green-600">
                                                    €{{ number_format(min($costs), 2) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">No pricing</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">No pricing</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">Select file</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-2">
                                    @if($branch->email)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            📧 Email
                                        </span>
                                    @endif
                                    @if($branch->phone)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            📞 Phone
                                        </span>
                                    @endif
                                    @if(!$branch->email && !$branch->phone)
                                        <span class="text-gray-400 text-xs">No contact</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center py-8">
            <div class="text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No branches available</h3>
                <p class="mt-1 text-sm text-gray-500">
                    No provider branches match the current file's criteria (city, service type, active status).
                </p>
            </div>
        </div>
    @endif
</div>

<script>
function toggleAllBranches(selectAllCheckbox) {
    const branchCheckboxes = document.querySelectorAll('.branch-checkbox');
    branchCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateSelectedBranches();
}

function updateSelectAllState() {
    const branchCheckboxes = document.querySelectorAll('.branch-checkbox');
    const selectAllCheckbox = document.getElementById('select-all-branches');
    
    const checkedCount = document.querySelectorAll('.branch-checkbox:checked').length;
    const totalCount = branchCheckboxes.length;
    
    if (checkedCount === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedCount === totalCount) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    }
    
    updateSelectedBranches();
}

function updateSelectedBranches() {
    const selectedBranches = Array.from(document.querySelectorAll('.branch-checkbox:checked'))
        .map(checkbox => checkbox.value);
    
    // Update the hidden field
    const hiddenField = document.querySelector('input[name="data.selected_branches"]');
    if (hiddenField) {
        hiddenField.value = JSON.stringify(selectedBranches);
    }
}

// Add event listeners to all branch checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const branchCheckboxes = document.querySelectorAll('.branch-checkbox');
    branchCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllState);
    });
});
</script>
