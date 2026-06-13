@php
    $livewire = $getLivewire();
@endphp

<div
    wire:key="appointment-branches-{{ $livewire->data['city_filter'] ?? 'default' }}"
    class="overflow-x-auto -mx-4 sm:mx-0"
    @if (! $livewire->distancesLoaded && ! $livewire->distancesLoading)
        wire:init="loadBranchDistances"
    @endif
>
    @if ($livewire->distancesLoading)
        <div class="mb-3 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="h-4 w-4" />
            <span>Sorting branches by driving distance…</span>
        </div>
    @endif

    @if ($livewire->branchTableRows === [])
        <p class="text-sm text-gray-500 dark:text-gray-400 py-4">No eligible provider branches found for this file.</p>
    @else
        <table class="w-full min-w-[960px] text-sm">
            <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200 font-semibold text-left dark:bg-white/5 dark:border-white/10">
                    <th class="px-2 py-2 w-10">
                        <input
                            type="checkbox"
                            wire:click="toggleSelectAll($event.target.checked)"
                            @checked($livewire->selectAllBranches)
                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                            title="Select all branches"
                        />
                    </th>
                    <th class="px-2 py-2">Branch Name</th>
                    <th class="px-2 py-2 w-16">Priority</th>
                    <th class="px-2 py-2 w-20">Cost</th>
                    <th class="px-2 py-2 w-24">Contact By</th>
                    <th class="px-2 py-2 w-24">Contact</th>
                    <th class="px-2 py-2 w-20">Phone</th>
                    <th class="px-2 py-2 w-24">Address</th>
                    <th class="px-2 py-2 w-20">Website</th>
                    <th class="px-2 py-2 w-20">Distance</th>
                    <th class="px-2 py-2 w-20">Request</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($livewire->branchTableRows as $row)
                    <tr wire:key="branch-row-{{ $row['id'] }}" class="border-b border-gray-100 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                        <td class="px-2 py-2 align-top">
                            <input
                                type="checkbox"
                                wire:model.live="selectedBranchIds"
                                value="{{ $row['id'] }}"
                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-2 py-2 align-top">
                            @include('filament.forms.components.branch-name-link', [
                                'branchName' => $row['branch_name'],
                                'branchId' => $row['id'],
                                'providerName' => $row['provider_name'],
                                'providerComment' => $row['provider_comment'],
                            ])
                        </td>
                        <td class="px-2 py-2 align-top">{{ $row['priority'] }}</td>
                        <td class="px-2 py-2 align-top">{{ $row['cost'] }}</td>
                        <td class="px-2 py-2 align-top">{{ $row['communication_method'] }}</td>
                        <td class="px-2 py-2 align-top">{!! $row['contact_html'] !!}</td>
                        <td class="px-2 py-2 align-top">
                            @include('filament.forms.components.copiable-field', [
                                'label' => 'phone',
                                'value' => $row['phone'],
                            ])
                        </td>
                        <td class="px-2 py-2 align-top">
                            @include('filament.forms.components.copiable-field', [
                                'label' => 'address',
                                'value' => $row['address'],
                            ])
                        </td>
                        <td class="px-2 py-2 align-top">
                            @include('filament.forms.components.copiable-field', [
                                'label' => 'website',
                                'value' => $row['website'],
                            ])
                        </td>
                        <td class="px-2 py-2 align-top text-gray-400">N/A</td>
                        <td class="px-2 py-2 align-top">
                            @include('filament.forms.components.request-appointment', [
                                'appointmentText' => $row['appointment_text'],
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
