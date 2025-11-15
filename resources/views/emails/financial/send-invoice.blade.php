<!DOCTYPE html>
<html>
<head>
    <title>Invoice</title>
</head>
<body>
    <h1>Invoice #{{ $invoice->invoice_number }}</h1>

    <p>Dear {{ $invoice->patient->client->financialContact->title }},</p>

    <p>Please find attached your invoice #{{ $invoice->invoice_number }}.</p>

    <p>Invoice Details:</p>
    <ul>
        <li>Date: {{ $invoice->invoice_date?->format('d/m/Y') }}</li>
        <li>Due Date: {{ $invoice->due_date?->format('d/m/Y') }}</li>
        <li>Amount: {{ number_format($invoice->total_amount, 2) }}€</li>
    </ul>

    <p>If you have any questions, or need any updates in the invoice details, please don't hesitate to contact us.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>