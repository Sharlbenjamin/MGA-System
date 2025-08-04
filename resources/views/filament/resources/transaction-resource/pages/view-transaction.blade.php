<x-filament-panels::page>
    <div class="space-y-6">
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
                            <x-filament::table.cell colspan="6" class="text-center text-gray-500">
                                No invoices found for this transaction.
                            </x-filament::table.cell>
                        </x-filament::table.row>
                    @endforelse
                @else
                    <x-slot name="header">
                        <x-filament::table.header-cell>Bill Name</x-filament::table.header-cell>
                        <x-filament::table.header-cell>File</x-filament::table.header-cell>
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
                            <x-filament::table.cell colspan="6" class="text-center text-gray-500">
                                No bills found for this transaction.
                            </x-filament::table.cell>
                        </x-filament::table.row>
                    @endforelse
                @endif
            </x-filament::table>
        </x-filament::section>
    </div>
</x-filament-panels::page> 