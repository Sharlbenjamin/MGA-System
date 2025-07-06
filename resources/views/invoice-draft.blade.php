<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Draft</title>
    @php
        $baseMargin = 200;
        $perRowMargin = 30;
        $totalRows = count($invoice->items);
        $dynamicMargin = $baseMargin + ($totalRows * $perRowMargin);
    @endphp
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f9f9f9;
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
            margin-bottom: 30px;
            padding: 20px;
        }

        .header img {
            width: 80px;
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
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        .info-section {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            background: #f4f4f4;
            margin-bottom: 20px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .invoice-table th, .invoice-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #253551;
            color: white;
        }

        .invoice-total {
            text-align: right;
            margin-top: 20px;
            padding: 20px;
            background: #f4f4f4;
            border-radius: 5px;
        }

        /* Watermark Styles */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.15;
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
        .footer {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            text-align: center;
            font-size: 14px;
            color: #555;
            border-top: 2px solid #191970;
            padding-top: 20px;
            background: rgba(255, 255, 255, 0.95);
            z-index: 10;
            box-sizing: border-box;
        }

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer ul li {
            margin: 5px 0;
        }

        .bold {
            font-weight: bold;
            color: #191970;
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
                <p><span class="bold">Invoice Number:</span> {{ $invoice->name }}</p>
                <p><span class="bold">Patient Name:</span> {{ $invoice->patient->name }}</p>
                <p><span class="bold">MGA Reference:</span> {{ $invoice->file->mga_reference ?? 'N/A' }}</p>
                <p><span class="bold">Client Reference:</span> {{ $invoice->file->client_reference ?? 'N/A' }}</p>
                <p><span class="bold">Issue Date:</span> {{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : $invoice->created_at->format('d/m/Y') }}</p>
                <p><span class="bold">Due Date:</span> {{ $invoice->due_date->format('d/m/Y') }}</p>
                <p><span class="bold">Service Type:</span> {{ $invoice->file->serviceType->name ?? 'N/A' }}</p>
                <p><span class="bold">Service Date:</span> {{ $invoice->file->service_date ? $invoice->file->service_date->format('d/m/Y') : 'N/A' }}</p>
                <p><span class="bold">Country:</span> {{ $invoice->file->country->name ?? 'N/A' }}</p>
                <p><span class="bold">City:</span> {{ $invoice->file->city->name ?? 'N/A' }}</p>
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
                <p><span class="bold">Discount:</span> €{{ number_format($invoice->discount, 2) }}</p>
                @endif

                <p style="font-size: 1.2em;"><span class="bold">Total Amount:</span> €{{ number_format($invoice->total_amount, 2) }}</p>

                @if($invoice->paid_amount > 0)
                <p><span class="bold">Paid Amount:</span> €{{ number_format($invoice->paid_amount, 2) }}</p>
                <p><span class="bold">Remaining Amount:</span> €{{ number_format($invoice->remaining_amount, 2) }}</p>
                @endif
            </div>
        </div>

        <div class="footer" style="margin-top: {{ $dynamicMargin }}px">
            <ul>
                <li>Company Name: Med Guard Assistance</li>
                <li>Email: mga.operation@medguarda.com</li>
                <li>Phone: +34 634 070 722</li>
                <li>Website: <a href="https://medguarda.com">medguarda.com</a></li>
            </ul>
        </div>
    </div>
</body>
</html>