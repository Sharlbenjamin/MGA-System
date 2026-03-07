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

        .signature-wrap {
            margin-top: 16px;
        }

        .signature-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
        }

        .signature-logo {
            width: 120px;
            height: auto;
        }

        .signature-divider {
            width: 2px;
            background: #FFC107;
            height: 120px;
        }

        .signature-text {
            font-size: 12px;
            color: #2c3e50;
            line-height: 1.5;
        }

        .signature-company {
            font-size: 14px;
            font-weight: 700;
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

    <div class="signature-wrap">
        <div class="signature-box">
            <img class="signature-logo" src="{{ $message->embed(storage_path('app/public/siglogo.png')) }}" alt="Med Guard Assistance Logo">
            <div class="signature-divider"></div>
            <div class="signature-text">
                <div class="signature-company">Med Guard Assistance</div>
                24/7 Email: mga.operation@medguarda.com<br>
                Website: <a href="https://medguarda.com" target="_blank">medguarda.com</a>
            </div>
        </div>
    </div>
</body>
</html>
