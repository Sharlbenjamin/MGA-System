<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 p-4">
        <p><strong>To:</strong> {{ $client->email ?? 'No financial email configured' }}</p>
        <p><strong>Subject:</strong> MGA x {{ $client->company_name }} Outstanding for {{ $monthName }} {{ $yearNumber }}</p>
        <p class="mt-2">Dear team,</p>
        <p>
            Please note that the total outstanding is <strong>{{ number_format($totalOutstanding, 2) }} EUR</strong>
            representing <strong>{{ $invoiceCount }}</strong> invoices.
        </p>
    </div>

    <div>
        <p class="mb-2 font-semibold">Outstanding Invoices</p>
        @if($invoices->isEmpty())
            <p class="text-sm text-warning-600">No outstanding invoices found for this client.</p>
        @else
            <div style="max-height: 320px; overflow: auto;" class="rounded-lg border border-gray-200">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-200 px-2 py-2 text-left">Invoice</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Patient</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Date</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Due Date</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">MGA Ref</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Client Ref</th>
                            <th class="border border-gray-200 px-2 py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td class="border border-gray-200 px-2 py-2">{{ $invoice->name }}</td>
                                <td class="border border-gray-200 px-2 py-2">{{ $invoice->patient?->name ?? '-' }}</td>
                                <td class="border border-gray-200 px-2 py-2">{{ $invoice->created_at?->format('d/m/Y') }}</td>
                                <td class="border border-gray-200 px-2 py-2">{{ $invoice->due_date?->format('d/m/Y') }}</td>
                                <td class="border border-gray-200 px-2 py-2">{{ $invoice->file?->mga_reference ?? '-' }}</td>
                                <td class="border border-gray-200 px-2 py-2">{{ $invoice->file?->client_reference ?? '-' }}</td>
                                <td class="border border-gray-200 px-2 py-2 text-right">{{ number_format((float) $invoice->total_amount, 2) }} EUR</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
