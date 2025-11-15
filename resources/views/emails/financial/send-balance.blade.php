<!DOCTYPE html>
<html>
<head>
    <title>Balance Statement</title>
    <style>
        .green-heading {
            color: #008000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1 class="green-heading">Balance Update</h1>

    <p>Dear {{ $invoices->first()->patient->client->financialContact->title }},</p>

    <p>{{ $msg }}</p>

    <p>Please find attached your balance statement containing the following invoices with a total amount of {{ number_format($invoices->sum('total_amount'), 2) }}€:</p>

    <table>
        <thead>
            <tr>
                <th>Invoice Number</th>
                <th>Patient Name</th>
                <th>MGA Reference</th>
                <th>Client Reference</th>
                <th>Invoice Date</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Total</th>
                <th>Paid Amount</th>
                <th style="width: 150px;">Remaining Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->name }}</td>
                    <td><span class="patient-name">{{ $invoice->patient->name }}</span></td>
                    <td>{{ $invoice->file->mga_reference }}</td>
                    <td>{{ $invoice->file->client_reference }}</td>
                    <td>{{ $invoice->invoice_date?->format('d/m/Y')}}</td>
                    <td>{{ $invoice->due_date?->format('d/m/Y')}}</td>
                    <td>{{ $invoice->status }}</td>
                    <td>{{ number_format($invoice->total_amount, 2) }}€</td>
                    <td>{{ number_format($invoice->paid_amount, 2) }}€</td>
                    <td>{{ number_format($invoice->total_amount - $invoice->paid_amount, 2) }}€</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>If you have any questions, or need any updates in the balance details, please don't hesitate to contact us.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>
