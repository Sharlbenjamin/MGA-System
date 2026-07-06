@php
    $livewire = $getLivewire();

    $offerStatusColors = [
        'Draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'Offered' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'Accepted' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    ];
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
        <table class="w-full min-w-[1200px] text-sm">
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
                    <th class="px-2 py-2">Branch</th>
                    <th class="px-2 py-2 w-16">Priority</th>
                    <th class="px-2 py-2 w-24">List cost</th>
                    <th class="px-2 py-2 w-24">Offer cost</th>
                    <th class="px-2 py-2 w-20">Fee</th>
                    <th class="px-2 py-2 w-20">Total</th>
                    <th class="px-2 py-2 w-24">Offer status</th>
                    <th class="px-2 py-2 w-28">Offer actions</th>
                    <th class="px-2 py-2 w-20">Distance</th>
                    <th class="px-2 py-2 w-20">Request</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($livewire->branchTableRows as $row)
                    @php
                        $branchId = $row['id'];
                        $inputs = $livewire->branchOfferInputs[$branchId] ?? ['offered_cost' => '', 'file_fee' => '0', 'notes' => ''];
                        $offeredCost = is_numeric($inputs['offered_cost'] ?? null) ? (float) $inputs['offered_cost'] : 0;
                        $fileFee = is_numeric($inputs['file_fee'] ?? null) ? (float) $inputs['file_fee'] : 0;
                        $rowTotal = $offeredCost + $fileFee;
                        $status = $row['latest_offer_status'] ?? null;
                        $statusClass = $status ? ($offerStatusColors[$status] ?? 'bg-gray-100 text-gray-800') : '';
                    @endphp
                    <tr wire:key="branch-row-{{ $branchId }}" class="border-b border-gray-100 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                        <td class="px-2 py-2 align-top">
                            <input
                                type="checkbox"
                                wire:model.live="selectedBranchIds"
                                value="{{ $branchId }}"
                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                            />
                        </td>
                        <td class="px-2 py-2 align-top">
                            @include('filament.forms.components.branch-name-link', [
                                'branchName' => $row['branch_name'],
                                'branchId' => $branchId,
                                'providerName' => $row['provider_name'],
                                'providerComment' => $row['provider_comment'],
                            ])
                        </td>
                        <td class="px-2 py-2 align-top">{{ $row['priority'] }}</td>
                        <td class="px-2 py-2 align-top text-gray-500">{{ $row['cost'] }}</td>
                        <td class="px-2 py-2 align-top">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.blur="branchOfferInputs.{{ $branchId }}.offered_cost"
                                class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                                placeholder="0"
                            />
                        </td>
                        <td class="px-2 py-2 align-top">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.blur="branchOfferInputs.{{ $branchId }}.file_fee"
                                class="w-16 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                                placeholder="0"
                            />
                        </td>
                        <td class="px-2 py-2 align-top font-medium">
                            €{{ number_format($rowTotal, 2) }}
                        </td>
                        <td class="px-2 py-2 align-top">
                            @if ($status)
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">{{ $status }}</span>
                                @if ($row['latest_offer_total'])
                                    <div class="mt-1 text-xs text-gray-500">Last: €{{ $row['latest_offer_total'] }}</div>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-2 py-2 align-top">
                            <div class="flex flex-col gap-1">
                                <button
                                    type="button"
                                    wire:click="saveBranchOffer({{ $branchId }})"
                                    class="text-xs font-medium text-primary-600 hover:underline"
                                >Save</button>
                                <button
                                    type="button"
                                    wire:click="shareBranchOffer({{ $branchId }})"
                                    class="text-xs font-medium text-primary-600 hover:underline"
                                >Offer</button>
                                <button
                                    type="button"
                                    wire:click="acceptBranchOffer({{ $branchId }})"
                                    class="text-xs font-medium text-success-600 hover:underline"
                                >Accept</button>
                            </div>
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
