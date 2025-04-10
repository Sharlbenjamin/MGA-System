<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Draft</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .invoice-details {
            margin-bottom: 20px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .invoice-table th, .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .invoice-total {
            text-align: right;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="invoice-header">
        <h1>INVOICE</h1>
        <p>Draft Version</p>
    </div>

    <div class="invoice-details">
        <p><strong>Invoice Number:</strong> {{ $invoice->name }}</p>
        <p><strong>Date:</strong> {{ $invoice->created_at->format('d/m/Y') }}</p>
        <p><strong>Due Date:</strong> {{ $invoice->due_date->format('d/m/Y') }}</p>
    </div>

    <div class="patient-details">
        <h3>Patient Information</h3>
        <p><strong>Name:</strong> {{ $invoice->patient->name }}</p>
        <p><strong>Address:</strong> {{ $invoice->patient->address }}</p>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td>€{{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total</strong></td>
                <td><strong>€{{ number_format($invoice->final_total, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="invoice-total">
        <p><strong>Total Amount:</strong> €{{ number_format($invoice->final_total, 2) }}</p>
        @if($invoice->paid_amount > 0)
            <p><strong>Paid Amount:</strong> €{{ number_format($invoice->paid_amount, 2) }}</p>
            <p><strong>Remaining Amount:</strong> €{{ number_format($invoice->remaining_amount, 2) }}</p>
        @endif
    </div>
</body>
</html>