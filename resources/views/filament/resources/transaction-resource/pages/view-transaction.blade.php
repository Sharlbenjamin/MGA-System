<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Widgets Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Files Widget -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Files</p>
                        <p class="text-2xl font-semibold text-blue-600">
                            {{ $this->record->invoices->flatMap->file->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Cost Widget -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Cost</p>
                        <p class="text-2xl font-semibold text-red-600">
                            €{{ number_format($this->record->invoices->flatMap->file->flatMap->bills->sum('total_amount'), 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Profit Widget -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Profit</p>
                        <p class="text-2xl font-semibold text-green-600">
                            €{{ number_format($this->record->invoices->flatMap->file->sum('profit'), 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Details Section -->
        <x-filament::section>
            <x-slot name="heading">
                Transaction Details
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <x-filament::field-wrapper>
                    <x-filament::field-wrapper.label>Name</x-filament::field-wrapper.label>
                    <div class="text-sm">{{ $this->record->name }}</div>
                </x-filament::field-wrapper>
                
                <x-filament::field-wrapper>
                    <x-filament::field-wrapper.label>Type</x-filament::field-wrapper.label>
                    <x-filament::badge 
                        :color="$this->record->type === 'Income' ? 'success' : ($this->record->type === 'Outflow' ? 'warning' : 'danger')"
                    >
                        {{ $this->record->type }}
                    </x-filament::badge>
                </x-filament::field-wrapper>
                
                <x-filament::field-wrapper>
                    <x-filament::field-wrapper.label>Amount</x-filament::field-wrapper.label>
                    <div class="text-sm font-semibold {{ $this->record->type === 'Income' ? 'text-success-600' : 'text-danger-600' }}">
                        €{{ number_format($this->record->amount, 2) }}
                    </div>
                </x-filament::field-wrapper>
                
                <x-filament::field-wrapper>
                    <x-filament::field-wrapper.label>Date</x-filament::field-wrapper.label>
                    <div class="text-sm">{{ $this->record->date->format('d/m/Y') }}</div>
                </x-filament::field-wrapper>
                
                <x-filament::field-wrapper>
                    <x-filament::field-wrapper.label>Bank Account</x-filament::field-wrapper.label>
                    <div class="text-sm">{{ $this->record->bankAccount->beneficiary_name ?? 'N/A' }}</div>
                </x-filament::field-wrapper>
                
                <x-filament::field-wrapper>
                    <x-filament::field-wrapper.label>Related Type</x-filament::field-wrapper.label>
                    <div class="text-sm">{{ $this->record->related_type }}</div>
                </x-filament::field-wrapper>
                
                <x-filament::field-wrapper>
                    <x-filament::field-wrapper.label>Bills Count</x-filament::field-wrapper.label>
                    <div class="text-sm">{{ $this->record->bills->count() }}</div>
                </x-filament::field-wrapper>
                
                @if($this->record->notes)
                <x-filament::field-wrapper class="md:col-span-2 lg:col-span-3">
                    <x-filament::field-wrapper.label>Notes</x-filament::field-wrapper.label>
                    <div class="text-sm">{{ $this->record->notes }}</div>
                </x-filament::field-wrapper>
                @endif
            </div>
        </x-filament::section>

        <!-- Table Section -->
        <x-filament::section>
            <x-slot name="heading">
                @if($this->record->type === 'Income')
                    Related Invoices
                @else
                    Related Bills
                @endif
            </x-slot>
            
            <x-filament::table>
                @if($this->record->type === 'Income')
                    <x-slot name="header">
                        <x-filament::table.header-cell>Invoice Name</x-filament::table.header-cell>
                        <x-filament::table.header-cell>File</x-filament::table.header-cell>
                        <x-filament::table.header-cell>MGA Reference</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Client</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Amount</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Status</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Date</x-filament::table.header-cell>
                    </x-slot>
                    
                    @forelse($this->record->invoices as $invoice)
                        <x-filament::table.row>
                            <x-filament::table.cell>
                                {{ $invoice->name }}
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                @if($invoice->file)
                                    <x-filament::link 
                                        href="{{ route('filament.admin.resources.files.view', $invoice->file->id) }}"
                                        color="primary"
                                    >
                                        {{ $invoice->file->name }}
                                    </x-filament::link>
                                @else
                                    N/A
                                @endif
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                @if($invoice->file)
                                    <x-filament::link 
                                        href="{{ route('filament.admin.resources.files.view', $invoice->file->id) }}"
                                        color="primary"
                                    >
                                        {{ $invoice->file->mga_reference ?? 'N/A' }}
                                    </x-filament::link>
                                @else
                                    N/A
                                @endif
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                {{ $invoice->file->patient->client->company_name ?? 'N/A' }}
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                <span class="font-semibold text-success-600">
                                    €{{ number_format($invoice->total_amount, 2) }}
                                </span>
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                <x-filament::badge 
                                    :color="$invoice->status === 'Paid' ? 'success' : ($invoice->status === 'Partial' ? 'warning' : 'danger')"
                                >
                                    {{ $invoice->status }}
                                </x-filament::badge>
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                {{ $invoice->created_at->format('d/m/Y') }}
                            </x-filament::table.cell>
                        </x-filament::table.row>
                    @empty
                        <x-filament::table.row>
                            <x-filament::table.cell colspan="7" class="text-center text-gray-500">
                                No invoices found for this transaction.
                            </x-filament::table.cell>
                        </x-filament::table.row>
                    @endforelse
                @else
                    <x-slot name="header">
                        <x-filament::table.header-cell>Bill Name</x-filament::table.header-cell>
                        <x-filament::table.header-cell>File</x-filament::table.header-cell>
                        <x-filament::table.header-cell>MGA Reference</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Provider</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Amount</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Status</x-filament::table.header-cell>
                        <x-filament::table.header-cell>Date</x-filament::table.header-cell>
                    </x-slot>
                    
                    @forelse($this->record->bills as $bill)
                        <x-filament::table.row>
                            <x-filament::table.cell>
                                {{ $bill->name }}
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                @if($bill->file)
                                    <x-filament::link 
                                        href="{{ route('filament.admin.resources.files.view', $bill->file->id) }}"
                                        color="primary"
                                    >
                                        {{ $bill->file->name }}
                                    </x-filament::link>
                                @else
                                    N/A
                                @endif
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                @if($bill->file)
                                    <x-filament::link 
                                        href="{{ route('filament.admin.resources.files.view', $bill->file->id) }}"
                                        color="primary"
                                    >
                                        {{ $bill->file->mga_reference ?? 'N/A' }}
                                    </x-filament::link>
                                @else
                                    N/A
                                @endif
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                @if($bill->provider)
                                    <x-filament::link 
                                        href="{{ route('filament.admin.resources.providers.view', $bill->provider->id) }}"
                                        color="primary"
                                    >
                                        {{ $bill->provider->name }}
                                    </x-filament::link>
                                @elseif($bill->providerBranch)
                                    <x-filament::link 
                                        href="{{ route('filament.admin.resources.provider-branches.view', $bill->providerBranch->id) }}"
                                        color="primary"
                                    >
                                        {{ $bill->providerBranch->name }}
                                    </x-filament::link>
                                @else
                                    N/A
                                @endif
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                <span class="font-semibold text-danger-600">
                                    €{{ number_format($bill->total_amount, 2) }}
                                </span>
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                <x-filament::badge 
                                    :color="$bill->status === 'Paid' ? 'success' : ($bill->status === 'Partial' ? 'warning' : 'danger')"
                                >
                                    {{ $bill->status }}
                                </x-filament::badge>
                            </x-filament::table.cell>
                            <x-filament::table.cell>
                                {{ $bill->created_at->format('d/m/Y') }}
                            </x-filament::table.cell>
                        </x-filament::table.row>
                    @empty
                        <x-filament::table.row>
                            <x-filament::table.cell colspan="7" class="text-center text-gray-500">
                                No bills found for this transaction.
                            </x-filament::table.cell>
                        </x-filament::table.row>
                    @endforelse
                @endif
            </x-filament::table>
        </x-filament::section>
    </div>
</x-filament-panels::page> 