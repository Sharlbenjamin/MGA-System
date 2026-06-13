<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; min-height: 100vh; position: relative; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
        .header img { width: 100px; height: auto; }
        .header h1 { flex: 1; text-align: center; font-size: 28px; text-transform: uppercase; color: #191970; margin: 0; }
        .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }
        .invoice-table th { background-color: #253551; color: white; padding: 10px 8px; }
        .invoice-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .invoice-total { text-align: right; margin-top: 20px; font-size: 1.1em; }
        .bold { font-weight: bold; color: #191970; }
        .footer { margin-top: 40px; border-top: 2px solid #191970; padding-top: 15px; font-size: 12px; color: #555; }
        .meta { margin-bottom: 20px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('siglogo.png') }}" alt="Logo">
        <h1>Payment Receipt</h1>
    </div>

    <div class="meta">
        <p><span class="bold">Client:</span> {{ $client?->company_name ?? 'N/A' }}</p>
        <p><span class="bold">Payment Date:</span> {{ $transaction->date?->format('d/m/Y') }}</p>
        <p><span class="bold">Amount Received:</span> €{{ number_format($transaction->amount, 2) }}</p>
        @if($transaction->reference)
            <p><span class="bold">Reference:</span> {{ $transaction->reference }}</p>
        @endif
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Invoice Number</th>
                <th>Patient Name</th>
                <th>Date</th>
                <th>Due Date</th>
                <th>MGA Reference</th>
                <th>Client Reference</th>
                <th>Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            @php $linkedTotal = 0; @endphp
            @foreach($invoices as $invoice)
                @php
                    $paid = (float) ($invoice->pivot->amount_paid ?? $invoice->total_amount);
                    $linkedTotal += $paid;
                @endphp
                <tr>
                    <td>{{ $invoice->name }}</td>
                    <td>{{ $invoice->patient?->name }}</td>
                    <td>{{ $invoice->invoice_date?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y') }}</td>
                    <td>{{ $invoice->due_date?->format('d/m/Y') }}</td>
                    <td>{{ $invoice->file?->mga_reference }}</td>
                    <td>{{ $invoice->file?->client_reference }}</td>
                    <td>€{{ number_format($paid, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="invoice-total">
        <p><span class="bold">Total Linked Invoices:</span> €{{ number_format($linkedTotal, 2) }}</p>
        <p><span class="bold">Bank Transaction Amount:</span> €{{ number_format($transaction->amount, 2) }}</p>
        <p><span class="bold">Difference:</span> €{{ number_format($transaction->amount - $linkedTotal, 2) }}</p>
    </div>

    @php $bankAccount = $invoices->first()?->bankAccount ?? $client?->bankAccounts?->first(); @endphp
    @if($bankAccount)
        <div class="footer">
            <p><span class="bold">Account Name:</span> {{ $bankAccount->beneficiary_name }}</p>
            <p><span class="bold">Country:</span> {{ $bankAccount->country?->name }}</p>
            <p><span class="bold">IBAN:</span> {{ $bankAccount->iban }}</p>
            <p><span class="bold">SWIFT:</span> {{ $bankAccount->swift }}</p>
        </div>
    @endif
</body>
</html>
