<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Balance Statement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            position: relative;
            min-height: 100vh;
        }

        /* Header Styles */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .header img {
            width: 100px;
            height: auto;
        }

        .header h1 {
            flex: 1;
            text-align: center;
            font-size: 30px;
            text-transform: uppercase;
            color: #191970;
            margin: 0;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }

        .invoice-table th {
            background-color: #253551;
            color: white;
            padding: 10px 8px;
        }

        .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .invoice-table .patient-name {
            font-size: 12px;
        }

        .invoice-total {
            text-align: right;
            margin-top: 20px;
            font-size: 1.2em;
        }

        .payment-note {
            margin: 20px 0;
            color: #191970;
            font-style: italic;
        }

        .bold {
            font-weight: bold;
            color: #191970;
        }

        /* Footer Styles */
        .footer {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            text-align: left;
            font-size: 12px;
            color: #555;
            border-top: 2px solid #191970;
            padding-top: 15px;
            background: transparent;
            z-index: 10;
            box-sizing: border-box;
        }

        .footer-grid {
            width: 100%;
            display: table;
        }

        .footer-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
            text-align: left;
        }

        .footer-column:last-child {
            padding-right: 0;
        }

        .footer p {
            margin: 2px 0;
        }

        /* Watermark Styles */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
            width: 100%;
            text-align: center;
        }

        .watermark img {
            width: 500px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="watermark">
        <img src="{{ public_path('siglogo.png') }}" alt="Watermark">
    </div>

    <div class="header">
        <img src="{{ public_path('siglogo.png') }}" alt="Medical Logo">
        <h1>BALANCE STATEMENT</h1>
    </div>

    <p><span class="bold">Invoice To:</span>
        @php
            $financialContact = $client->financialContact();
            $billTo = $financialContact ? $financialContact->title : $client->name;
        @endphp
        {{ $billTo }}
    </p>

    <p class="payment-note">Please note that prompt payment of these outstanding invoices would be greatly appreciated. If you have already processed these payments, kindly disregard this statement.</p>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Invoice Number</th>
                <th>Patient Name</th>
                <th>Date</th>
                <th>Due Date</th>
                <th>MGA Reference</th>
                <th>Client Reference</th>
                <th style="width: 150px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($client->invoices()->where('status', 'Unpaid')->get() as $invoice)
            <tr>
                <td>{{ $invoice->name }}</td>
                <td><span class="patient-name">{{ $invoice->patient->name }}</span></td>
                <td>{{ $invoice->created_at?->format('d/m/Y') }}</td>
                <td>{{ $invoice->due_date?->format('d/m/Y') }}</td>
                <td>{{ $invoice->file->mga_reference }}</td>
                <td>{{ $invoice->file->client_reference }}</td>
                <td>€{{ number_format($invoice->total_amount, 2) }}</td>
            </tr>
            @php $total += $invoice->total_amount; @endphp
            @endforeach
        </tbody>
    </table>

    <div class="invoice-total">
        <span class="bold">Total Outstanding Amount:</span> €{{ number_format($total, 2) }}
    </div>


    <div class="footer">
        <div class="footer-grid">
            <div class="footer-column">
                <p><span class="bold">Company Name:</span>  Med Guard Assistance</p>
                <p><span class="bold">Phone:</span>  +34 634 070 722</p>
                <p><span class="bold">Email:</span>  mga.operation@medguarda.com</p>
                <p><span class="bold">Website:</span>  <a href="https://medguarda.com">medguarda.com</a></p>
            </div>

            <div class="footer-column">
                @php
                    $firstInvoice = $client->invoices()->where('status', 'Unpaid')->first();
                    $bankAccount = $firstInvoice?->bankAccount;
                @endphp
                @if($bankAccount)
                    <p><span class="bold">Account Name:</span> {{ $bankAccount->beneficiary_name }}</p>
                    <p><span class="bold">Country:</span> {{ $bankAccount->country?->name }}</p>
                    <p><span class="bold">IBAN:</span> {{ $bankAccount->iban }}</p>
                    <p><span class="bold">SWIFT:</span> {{ $bankAccount->swift }}</p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>