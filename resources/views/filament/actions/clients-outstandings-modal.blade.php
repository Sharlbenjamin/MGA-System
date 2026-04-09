<div class="space-y-4">
    @if($clients->isEmpty())
        <div class="text-sm text-gray-600">
            No clients currently have outstanding invoices.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="px-3 py-2 text-left font-medium">Client</th>
                        <th class="px-3 py-2 text-left font-medium">Total Outstanding</th>
                        <th class="px-3 py-2 text-left font-medium">Last Outstanding Sent</th>
                        <th class="px-3 py-2 text-left font-medium">Invoices Not Sent</th>
                        <th class="px-3 py-2 text-left font-medium">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $client->company_name }}</td>
                            <td class="px-3 py-2">EUR {{ number_format((float) $client->total_outstanding, 2) }}</td>
                            <td class="px-3 py-2">
                                {{ $client->last_outstanding_sent_date ? \Carbon\Carbon::parse($client->last_outstanding_sent_date)->format('d-m-Y') : '-' }}
                            </td>
                            <td class="px-3 py-2">{{ (int) $client->unsent_invoices_count }}</td>
                            <td class="px-3 py-2">
                                <x-filament::button
                                    size="sm"
                                    color="warning"
                                    wire:click="sendOutstandingBalanceForClient({{ $client->id }})"
                                >
                                    Send Outstanding Balance
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
