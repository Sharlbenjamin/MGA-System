<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Widgets Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Files Widget -->
            <x-filament::widget>
                <x-filament::widget.header>
                    <x-filament::widget.header.heading>
                        Files
                    </x-filament::widget.header.heading>
                    <x-filament::widget.header.icon 
                        icon="heroicon-o-document"
                        color="primary"
                    />
                </x-filament::widget.header>
                <x-filament::widget.content>
                    <div class="text-2xl font-semibold text-primary-600">
                        {{ $this->record->invoices->flatMap->file->count() }}
                    </div>
                </x-filament::widget.content>
            </x-filament::widget>

            <!-- Cost Widget -->
            <x-filament::widget>
                <x-filament::widget.header>
                    <x-filament::widget.header.heading>
                        Total Cost
                    </x-filament::widget.header.heading>
                    <x-filament::widget.header.icon 
                        icon="heroicon-o-currency-dollar"
                        color="danger"
                    />
                </x-filament::widget.header>
                <x-filament::widget.content>
                    <div class="text-2xl font-semibold text-danger-600">
                        €{{ number_format($this->record->invoices->flatMap->file->flatMap->bills->sum('total_amount'), 2) }}
                    </div>
                </x-filament::widget.content>
            </x-filament::widget>

            <!-- Profit Widget -->
            <x-filament::widget>
                <x-filament::widget.header>
                    <x-filament::widget.header.heading>
                        Total Profit
                    </x-filament::widget.header.heading>
                    <x-filament::widget.header.icon 
                        icon="heroicon-o-arrow-trending-up"
                        color="success"
                    />
                </x-filament::widget.header>
                <x-filament::widget.content>
                    <div class="text-2xl font-semibold text-success-600">
                        €{{ number_format($this->record->invoices->flatMap->file->sum('profit'), 2) }}
                    </div>
                </x-filament::widget.content>
            </x-filament::widget>

            <!-- Additional Widgets for Invoices -->
            @if($this->record->type === 'Income')
            <!-- Invoices Count Widget -->
            <x-filament::widget>
                <x-filament::widget.header>
                    <x-filament::widget.header.heading>
                        Invoices
                    </x-filament::widget.header.heading>
                    <x-filament::widget.header.icon 
                        icon="heroicon-o-document-text"
                        color="primary"
                    />
                </x-filament::widget.header>
                <x-filament::widget.content>
                    <div class="text-2xl font-semibold text-primary-600">
                        {{ $this->record->invoices->count() }}
                    </div>
                </x-filament::widget.content>
            </x-filament::widget>

            <!-- Invoices Total Widget -->
            <x-filament::widget>
                <x-filament::widget.header>
                    <x-filament::widget.header.heading>
                        Invoices Total
                    </x-filament::widget.header.heading>
                    <x-filament::widget.header.icon 
                        icon="heroicon-o-currency-dollar"
                        color="success"
                    />
                </x-filament::widget.header>
                <x-filament::widget.content>
                    <div class="text-2xl font-semibold text-success-600">
                        €{{ number_format($this->record->invoices->sum('total_amount'), 2) }}
                    </div>
                </x-filament::widget.content>
            </x-filament::widget>

            <!-- Paid Invoices Widget -->
            <x-filament::widget>
                <x-filament::widget.header>
                    <x-filament::widget.header.heading>
                        Paid Invoices
                    </x-filament::widget.header.heading>
                    <x-filament::widget.header.icon 
                        icon="heroicon-o-check-circle"
                        color="success"
                    />
                </x-filament::widget.header>
                <x-filament::widget.content>
                    <div class="text-2xl font-semibold text-success-600">
                        {{ $this->record->invoices->where('status', 'Paid')->count() }}
                    </div>
                </x-filament::widget.content>
            </x-filament::widget>
            @endif
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
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    @if($this->record->type === 'Income')
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3">Invoice Name</th>
                                <th scope="col" class="px-6 py-3">File</th>
                                <th scope="col" class="px-6 py-3">MGA Reference</th>
                                <th scope="col" class="px-6 py-3">Client</th>
                                <th scope="col" class="px-6 py-3">Amount</th>
                                <th scope="col" class="px-6 py-3">Status</th>
                                <th scope="col" class="px-6 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->record->invoices as $invoice)
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $invoice->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($invoice->file)
                                            <a href="{{ route('filament.admin.resources.files.view', $invoice->file->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $invoice->file->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($invoice->file)
                                            <a href="{{ route('filament.admin.resources.files.view', $invoice->file->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $invoice->file->mga_reference ?? 'N/A' }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $invoice->file->patient->client->company_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-semibold text-green-600">
                                            €{{ number_format($invoice->total_amount, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $invoice->status === 'Paid' ? 'bg-green-100 text-green-800' : 
                                               ($invoice->status === 'Partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ $invoice->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $invoice->created_at->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        No invoices found for this transaction.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    @else
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3">Bill Name</th>
                                <th scope="col" class="px-6 py-3">File</th>
                                <th scope="col" class="px-6 py-3">MGA Reference</th>
                                <th scope="col" class="px-6 py-3">Provider</th>
                                <th scope="col" class="px-6 py-3">Amount</th>
                                <th scope="col" class="px-6 py-3">Status</th>
                                <th scope="col" class="px-6 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->record->bills as $bill)
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $bill->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($bill->file)
                                            <a href="{{ route('filament.admin.resources.files.view', $bill->file->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $bill->file->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($bill->file)
                                            <a href="{{ route('filament.admin.resources.files.view', $bill->file->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $bill->file->mga_reference ?? 'N/A' }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($bill->provider)
                                            <a href="{{ route('filament.admin.resources.providers.view', $bill->provider->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $bill->provider->name }}
                                            </a>
                                        @elseif($bill->providerBranch)
                                            <a href="{{ route('filament.admin.resources.provider-branches.view', $bill->providerBranch->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $bill->providerBranch->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-semibold text-red-600">
                                            €{{ number_format($bill->total_amount, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $bill->status === 'Paid' ? 'bg-green-100 text-green-800' : 
                                               ($bill->status === 'Partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ $bill->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $bill->created_at->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        No bills found for this transaction.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    @endif
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page> 