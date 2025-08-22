<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Widgets Section -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                        <p class="text-2xl font-semibold" style="color: #2563eb !important;">
                            {{ $filesCount }}
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
                        <p class="text-sm font-medium text-red-500">Total Cost</p>
                        <p class="text-2xl font-semibold" style="color: #dc2626 !important;">
                            €{{ number_format($totalCost, 2) }}
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
                        <p class="text-sm font-medium text-green-500">Total Profit</p>
                        <p class="text-2xl font-semibold" style="color: #16a34a !important;">
                            €{{ number_format($totalProfit, 2) }}
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Invoices Widget (for Income transactions) -->
            @if($this->record->type === 'Income')
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-purple-500">Total Invoices</p>
                        <p class="text-2xl font-semibold" style="color: #9333ea !important;">
                            €{{ number_format($totalInvoices, 2) }}
                        </p>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Bills Widget (for Outflow/Expense transactions) -->
            @if($this->record->type === 'Outflow' || $this->record->type === 'Expense')
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 rounded-lg">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-orange-500">Bills Count</p>
                        <p class="text-2xl font-semibold" style="color: #ea580c !important;">
                            {{ $this->record->bills->count() }}
                        </p>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Bank Charges Widget -->
            @if($this->record->bank_charges > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Bank Charges</p>
                        <p class="text-2xl font-semibold" style="color: #6b7280 !important;">
                            €{{ number_format($this->record->bank_charges, 2) }}
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Transaction Details Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Transaction Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Name</label>
                    <div class="text-sm">{{ $this->record->name }}</div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Type</label>
                    <div class="text-sm">
                        <span class="px-2 py-1 text-xs rounded-full 
                            {{ $this->record->type === 'Income' ? 'bg-green-100 text-green-800' : 
                               ($this->record->type === 'Outflow' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ $this->record->type }}
                        </span>
                    </div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Amount</label>
                    <div class="text-sm font-semibold {{ $this->record->type === 'Income' ? 'text-green-600' : 'text-red-600' }}">
                        €{{ number_format($this->record->amount, 2) }}
                    </div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Date</label>
                    <div class="text-sm">{{ $this->record->date->format('d/m/Y') }}</div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Bank Account</label>
                    <div class="text-sm">{{ $this->record->bankAccount->beneficiary_name ?? 'N/A' }}</div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Related Type</label>
                    <div class="text-sm">{{ $this->record->related_type }}</div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Related Entity</label>
                    <div class="text-sm">
                        @if($this->record->related_type === 'Client')
                            @php
                                $client = \App\Models\Client::find($this->record->related_id);
                            @endphp
                            @if($client)
                                <a href="{{ route('filament.admin.resources.clients.view', $client->id) }}" 
                                   class="text-blue-600 hover:text-blue-800 underline">
                                    {{ $client->company_name ?? $client->name }}
                                </a>
                            @else
                                N/A
                            @endif
                        @elseif($this->record->related_type === 'Provider')
                            @php
                                $provider = \App\Models\Provider::find($this->record->related_id);
                            @endphp
                            @if($provider)
                                <a href="{{ route('filament.admin.resources.providers.view', $provider->id) }}" 
                                   class="text-blue-600 hover:text-blue-800 underline">
                                    {{ $provider->name }}
                                </a>
                            @else
                                N/A
                            @endif
                        @elseif($this->record->related_type === 'Branch')
                            @php
                                $branch = \App\Models\ProviderBranch::find($this->record->related_id);
                            @endphp
                            @if($branch)
                                <a href="{{ route('filament.admin.resources.provider-branches.view', $branch->id) }}" 
                                   class="text-blue-600 hover:text-blue-800 underline">
                                    {{ $branch->name }}
                                </a>
                            @else
                                N/A
                            @endif
                        @else
                            N/A
                        @endif
                    </div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Bills Count</label>
                    <div class="text-sm">{{ $this->record->bills->count() }}</div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Status</label>
                    <div class="text-sm">
                        <span class="px-2 py-1 text-xs rounded-full 
                            {{ $this->record->status === 'Completed' ? 'bg-green-100 text-green-800' : 
                               ($this->record->status === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ $this->record->status ?? 'Unknown' }}
                        </span>
                    </div>
                </div>
                
                @if($this->record->notes)
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="text-sm font-medium text-gray-500">Notes</label>
                    <div class="text-sm">{{ $this->record->notes }}</div>
                </div>
                @endif
                
                @if($this->record->charges_covered_by_client)
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="text-sm font-medium text-gray-500">Charges Covered by Client</label>
                    <div class="text-sm">
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            Yes
                        </span>
                    </div>
                </div>
                @endif
                
                @if($this->record->attachment_path)
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="text-sm font-medium text-gray-500">Document</label>
                    <div class="text-sm">
                        @if($this->record->isGoogleDriveAttachment())
                            <a href="{{ $this->record->attachment_path }}" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 underline">
                                {{ $this->record->getAttachmentDisplayText() }}
                            </a>
                        @else
                            <a href="{{ Storage::url($this->record->attachment_path) }}" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 underline">
                                {{ $this->record->getAttachmentDisplayText() }}
                            </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">
                    @if($this->record->type === 'Income')
                        Related Invoices
                    @else
                        Related Bills
                    @endif
                </h3>
            </div>
            
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
                                                {{ $invoice->file->name ?? 'File #' . $invoice->file->id }}
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
                                        {{ $invoice->file->patient->client->company_name ?? ($invoice->file->patient->client->name ?? 'N/A') }}
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
                                                {{ $bill->file->name ?? 'File #' . $bill->file->id }}
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
                                            <a href="{{ route('filament.admin.resources.providers.overview', $bill->provider->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $bill->provider->name }}
                                            </a>
                                        @elseif($bill->branch)
                                            <a href="{{ route('filament.admin.resources.provider-branches.overview', $bill->branch->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 underline">
                                                {{ $bill->branch->name }}
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
        </div>
    </div>
</x-filament-panels::page> 