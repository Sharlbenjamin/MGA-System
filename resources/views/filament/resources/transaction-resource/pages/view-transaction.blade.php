<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Transaction Details Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Transaction Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Name</label>
                    <p class="text-sm">{{ $this->record->name }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Type</label>
                    <p class="text-sm">
                        <span class="px-2 py-1 text-xs rounded-full 
                            {{ $this->record->type === 'Income' ? 'bg-green-100 text-green-800' : 
                               ($this->record->type === 'Outflow' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ $this->record->type }}
                        </span>
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Amount</label>
                    <p class="text-sm font-semibold 
                        {{ $this->record->type === 'Income' ? 'text-green-600' : 'text-red-600' }}">
                        €{{ number_format($this->record->amount, 2) }}
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Date</label>
                    <p class="text-sm">{{ $this->record->date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Bank Account</label>
                    <p class="text-sm">{{ $this->record->bankAccount->beneficiary_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Related Type</label>
                    <p class="text-sm">{{ $this->record->related_type }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Bills Count</label>
                    <p class="text-sm">{{ $this->record->bills->count() }}</p>
                </div>
                @if($this->record->notes)
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="text-sm font-medium text-gray-500">Notes</label>
                    <p class="text-sm">{{ $this->record->notes }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Widgets Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Files Widget -->
            <div class="bg-white rounded-lg shadow p-4">
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
            <div class="bg-white rounded-lg shadow p-4">
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
            <div class="bg-white rounded-lg shadow p-4">
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



            <!-- Additional Widgets for Invoices -->
            @if($this->record->type === 'Income')
            <!-- Invoices Count Widget -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Invoices</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            {{ $this->record->invoices->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Invoices Total Widget -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Invoices Total</p>
                        <p class="text-2xl font-semibold text-green-600">
                            €{{ number_format($this->record->invoices->sum('total_amount'), 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Paid Invoices Widget -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Paid Invoices</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            {{ $this->record->invoices->where('status', 'Paid')->count() }}
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">
                    @if($this->record->type === 'Income')
                        Related Invoices
                    @else
                        Related Bills
                    @endif
                </h3>
            </div>
            <div class="overflow-x-auto w-full">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if($this->record->type === 'Income')
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            @else
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bill Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @if($this->record->type === 'Income')
                            @forelse($this->record->invoices as $invoice)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $invoice->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($invoice->file)
                                            <a href="{{ route('filament.admin.resources.files.edit', $invoice->file->id) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ $invoice->file->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $invoice->file->patient->client->company_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                        €{{ number_format($invoice->total_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $invoice->status === 'Paid' ? 'bg-green-100 text-green-800' : 
                                               ($invoice->status === 'Partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ $invoice->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $invoice->created_at->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No invoices found for this transaction.
                                    </td>
                                </tr>
                            @endforelse
                        @else
                            @forelse($this->record->bills as $bill)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $bill->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($bill->file)
                                            <a href="{{ route('filament.admin.resources.files.edit', $bill->file->id) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ $bill->file->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($bill->provider)
                                            <a href="{{ route('filament.admin.resources.providers.edit', $bill->provider->id) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ $bill->provider->name }}
                                            </a>
                                        @elseif($bill->providerBranch)
                                            <a href="{{ route('filament.admin.resources.provider-branches.edit', $bill->providerBranch->id) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ $bill->providerBranch->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-red-600">
                                        €{{ number_format($bill->total_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $bill->status === 'Paid' ? 'bg-green-100 text-green-800' : 
                                               ($bill->status === 'Partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ $bill->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $bill->created_at->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No bills found for this transaction.
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page> 