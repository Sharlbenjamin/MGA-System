<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 p-4">
        <p><strong>To:</strong> {{ $providerEmail ?: 'No provider email configured' }}</p>
        <p><strong>Subject:</strong> Proof of Payment - {{ $transaction->name ?? ('Transaction #' . $transaction->id) }}</p>
        <p class="mt-2">Dear team,</p>
        <p>Find attached a proof of payment with the following details:</p>
    </div>

    <div>
        <p class="mb-2 font-semibold">Proof Details</p>
        <div style="max-height: 320px; overflow: auto;" class="rounded-lg border border-gray-200">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-200 px-2 py-2 text-left">Patient name</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Our reference</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Bill number</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Bill date</th>
                        <th class="border border-gray-200 px-2 py-2 text-right">Bill amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        <tr>
                            <td class="border border-gray-200 px-2 py-2">{{ $bill->file?->patient?->name ?? '-' }}</td>
                            <td class="border border-gray-200 px-2 py-2">{{ $bill->file?->mga_reference ?? '-' }}</td>
                            <td class="border border-gray-200 px-2 py-2">{{ $bill->name ?? '-' }}</td>
                            <td class="border border-gray-200 px-2 py-2">{{ $bill->bill_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-right">{{ number_format((float) $bill->total_amount, 2) }} EUR</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="border border-gray-200 px-2 py-2">No bill details available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-4">
        <p style="margin: 0;">Best Regards,</p>
        <p style="margin: 0;">Med Guard Assistance</p>
        @if($signature)
            @if(!empty($signature->job_title))
                <p style="margin: 0;">{{ $signature->job_title }}</p>
            @endif
            @if(!empty($signature->department))
                <p style="margin: 0;">{{ $signature->department }} Department</p>
            @endif
            @if(!empty($signature->work_phone))
                <p style="margin: 0;">Tel: {{ $signature->work_phone }}</p>
            @endif
        @endif
        <p style="margin: 0;">24/7 Email: mga.operation@medguarda.com</p>
        <p style="margin: 0;">Website: medguarda.com</p>
    </div>
</div>
