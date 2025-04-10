<!DOCTYPE html>
<html>
<head>
    <title>Invoice</title>
</head>
<body>
    <h1>Invoice #{{ $invoice->invoice_number }}</h1>

    <p>Dear {{ $invoice->patient->name }},</p>

    <p>Please find attached your invoice #{{ $invoice->invoice_number }}.</p>

    <p>Invoice Details:</p>
    <ul>
        <li>Date: {{ $invoice->date?->format('d/m/Y') }}</li>
        <li>Amount: {{ number_format($invoice->amount, 2) }}</li>
    </ul>

    <p>If you have any questions, please don't hesitate to contact us.</p>

    <p>Thank you for your business!</p>

    <p>Best regards,<br>
    Your Healthcare Provider</p>
</body>
</html>