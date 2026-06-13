<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Provider Payment Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .header img { width: 100px; height: auto; }
        .header h1 { flex: 1; text-align: center; font-size: 26px; text-transform: uppercase; color: #191970; margin: 0; }
        .meta, .address { margin-bottom: 16px; line-height: 1.6; font-size: 13px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 11px; }
        .invoice-table th { background-color: #253551; color: white; padding: 8px; }
        .invoice-table td { border: 1px solid #ddd; padding: 7px; }
        .totals { text-align: right; margin-top: 16px; font-size: 13px; }
        .bold { font-weight: bold; color: #191970; }
        .footer { margin-top: 30px; border-top: 2px solid #191970; padding-top: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('siglogo.png') }}" alt="Logo">
        <h1>Provider Payment</h1>
    </div>

    <div class="meta">
        <p><span class="bold">Provider:</span> {{ $provider?->name ?? 'N/A' }}</p>
        <p><span class="bold">Transaction Date:</span> {{ $transaction->date?->format('d/m/Y') }}</p>
        <p><span class="bold">Amount:</span> €{{ number_format($transaction->amount, 2) }}</p>
        @if($transaction->reference)
            <p><span class="bold">Reference:</span> {{ $transaction->reference }}</p>
        @endif
    </div>

    <div class="address">
        <p><span class="bold">Provider Address</span></p>
        <p>{{ $provider?->country?->name ?? '' }}</p>
        <p>{{ $branch?->city?->name ?? '' }} {{ $branch?->address ?? '' }}</p>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Patient</th>
                <th>Service Date</th>
                <th>Service Type</th>
                <th>Bill Reference</th>
                <th>Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            @php $linkedTotal = 0; @endphp
            @foreach($bills as $bill)
                @php
                    $paid = (float) ($bill->pivot->amount_paid ?? $bill->total_amount);
                    $linkedTotal += $paid;
                @endphp
                <tr>
                    <td>{{ $bill->file?->patient?->name }}</td>
                    <td>{{ $bill->file?->service_date?->format('d/m/Y') }}</td>
                    <td>{{ $bill->file?->serviceType?->name ?? '' }}</td>
                    <td>{{ $bill->name }}</td>
                    <td>€{{ number_format($paid, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p><span class="bold">Total Linked Bills:</span> €{{ number_format($linkedTotal, 2) }}</p>
        <p><span class="bold">Bank Transaction Amount:</span> €{{ number_format($transaction->amount, 2) }}</p>
        <p><span class="bold">Difference:</span> €{{ number_format($transaction->amount - $linkedTotal, 2) }}</p>
    </div>

    @if($bankAccount)
        <div class="footer">
            <p><span class="bold">Beneficiary:</span> {{ $bankAccount->beneficiary_name }}</p>
            <p><span class="bold">IBAN:</span> {{ $bankAccount->iban }}</p>
            <p><span class="bold">SWIFT:</span> {{ $bankAccount->swift }}</p>
            @if($bankAccount->beneficiary_address)
                <p><span class="bold">Address:</span> {{ $bankAccount->beneficiary_address }}</p>
            @endif
        </div>
    @endif
</body>
</html>
