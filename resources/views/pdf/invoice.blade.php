<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            position: relative;
            min-height: 100vh;
        }

        .page-wrapper {
            position: relative;
            min-height: 100vh;
            padding-bottom: 120px;
        }

        /* Header Styles */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 5px 10px;
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

        /* Content Styles */
        .container {
            width: 90%;
            margin: auto;
            background: transparent;
            padding: 10px;
            border-radius: 10px;
            box-shadow: none;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-section {
            border: 2px solid #191970;
            padding: 10px;
            border-radius: 5px;
            background: transparent;
            margin-bottom: 15px;
        }

        .info-section h3 {
            color: #191970;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 5px 0;
        }

        .invoice-table th, .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #253551;
            color: white;
        }

        .invoice-total {
            text-align: right;
            margin-top: 5px;
            padding: 15px;
            background: transparent;
            border-radius: 5px;
            border: none;
        }

        /* Watermark Styles */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            z-index: 1;
            pointer-events: none;
            width: 100%;
            text-align: center;
        }

        .watermark img {
            width: 500px;
            height: auto;
        }

        /* Footer Styles */
        @php
            $baseMargin = 200;
            $perRowMargin = 30;
            $totalRows = count($invoice->items);
            $dynamicMargin = $baseMargin + ($totalRows * $perRowMargin);
        @endphp

        .footer {
            position: absolute;
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
            margin-top: {{ $dynamicMargin }}px;
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

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
        }

        .footer ul li {
            margin: 5px 0;
        }

        .bold {
            font-weight: bold;
            color: #191970;
        }

        .info-details-flex {
            width: 100%;
            display: table;
        }

        .info-details-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }

        .info-details-column:last-child {
            padding-right: 0;
        }

        .info-details-column p {
            margin: 10px 0;
        }

        .footer .bold {
            font-size: 12px;
        }

        .footer p {
            margin: 2px 0;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="watermark">
            <img src="{{ public_path('siglogo.png') }}" alt="Watermark">
        </div>

        <div class="container">
            <div class="header">
                <img src="{{ public_path('siglogo.png') }}" alt="Medical Logo">
                <h1>INVOICE</h1>
            </div>

            <div class="info-section">
                <h3><span class="bold">Invoice To:</span>
                    @php
                        $financialContact = $invoice->patient->client->financialContact;
                        $billTo = $financialContact ? $financialContact->title : $invoice->patient->client->name;
                    @endphp
                    {{ $billTo }}
                </h3>
            </div>

            <div class="info-grid">
                <div class="info-section">
                    <h3>Invoice Details</h3>
                    <div class="info-details-flex">
                        <div class="info-details-column">
                            <p><span class="bold">Invoice Number:</span> {{ $invoice->name }}</p>
                            <p><span class="bold">Patient:</span> {{ $invoice->patient->name }}</p>
                            <p><span class="bold">Service Type:</span> {{ $invoice->file->serviceType->name }}</p>
                            <p><span class="bold">Service Date:</span> {{ $invoice->file->service_date?->format('d/m/Y') }}</p>
                        </div>
                        <div class="info-details-column">
                            <p><span class="bold">Client Reference:</span> {{ $invoice->file->client_reference }}</p>
                            <p><span class="bold">Date:</span> {{ $invoice->created_at?->format('d/m/Y') }}</p>
                            <p><span class="bold">Due Date:</span> {{ $invoice->due_date?->format('d/m/Y') }}</p>
                            <p><span class="bold">Country:</span> {{ $invoice->file->country->name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="width: 150px;">Amount</th>
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
            </table>

            <div class="invoice-total">
                <p><span class="bold">Subtotal:</span> €{{ number_format($invoice->subtotal, 2) }}</p>

                @if($invoice->discount > 0)
                <p><span class="bold">Discount:</span> €{{ '-' . number_format($invoice->discount, 2) }}</p>
                @endif

                <p style="font-size: 1.2em;"><span class="bold">Total Amount:</span> €{{ number_format($invoice->total_amount, 2) }}</p>

            </div>
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
                    <p><span class="bold">Account Name:</span> {{ $invoice->bankAccount->beneficiary_name }}</p>
                    <p><span class="bold">Country:</span> {{ $invoice->bankAccount->country?->name }}</p>
                    <p><span class="bold">IBAN:</span> {{ $invoice->bankAccount->iban }}</p>
                    <p><span class="bold">SWIFT:</span> {{ $invoice->bankAccount->swift }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
