<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .invoice-details {
            margin-bottom: 30px;
        }
        .patient-details {
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .totals {
            text-align: right;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
    </div>

    <div class="invoice-details">
        <p><strong>Invoice Number:</strong> #{{ $invoice->id }}</p>
        <p><strong>Date:</strong> {{ $invoice->created_at->format('d/m/Y') }}</p>
        <p><strong>Due Date:</strong> {{ $invoice->due_date->format('d/m/Y') }}</p>
        <p><strong>Status:</strong> {{ $invoice->status }}</p>
    </div>

    <div class="patient-details">
        <h3>Patient Information</h3>
        <p><strong>Name:</strong> {{ $invoice->patient->name }}</p>
        <p><strong>Email:</strong> {{ $invoice->patient->email }}</p>
        <p><strong>Phone:</strong> {{ $invoice->patient->phone }}</p>
        <p><strong>Address:</strong> {{ $invoice->patient->address }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->name }}</td>
                <td>€{{ number_format($invoice->final_total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <p><strong>Total Amount:</strong> €{{ number_format($invoice->final_total, 2) }}</p>
        <p><strong>Paid Amount:</strong> €{{ number_format($invoice->paid_amount, 2) }}</p>
        <p><strong>Remaining Amount:</strong> €{{ number_format($invoice->remaining_amount, 2) }}</p>
    </div>
</body>
</html>