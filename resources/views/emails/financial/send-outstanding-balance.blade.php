<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Outstanding Balance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <p>Dear team,</p>

    <p>
        Please note that the total outstanding is <strong>{{ number_format($totalOutstanding, 2) }} EUR</strong>
        representing <strong>{{ $invoiceCount }}</strong> invoices.
    </p>

    <p>
        Please find attached the details of the balance statement.
        Also, find below a list of the total outstanding.
    </p>

    <table>
        <thead>
            <tr>
                <th>Invoice Number</th>
                <th>Patient Name</th>
                <th>Date</th>
                <th>Due Date</th>
                <th>MGA Reference</th>
                <th>Client Reference</th>
                <th>Amount (EUR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->name }}</td>
                    <td>{{ $invoice->patient?->name }}</td>
                    <td>{{ $invoice->created_at?->format('d/m/Y') }}</td>
                    <td>{{ $invoice->due_date?->format('d/m/Y') }}</td>
                    <td>{{ $invoice->file?->mga_reference }}</td>
                    <td>{{ $invoice->file?->client_reference }}</td>
                    <td>{{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Kind Regards.</p>
</body>
</html>
