<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $billName ?: 'Bill' }}</title>
    <style>
        @page {
            margin: 18mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            color: #000000;
            background: #ffffff;
        }

        h1 {
            font-size: 16px;
            font-weight: normal;
            margin: 0 0 18px;
        }

        p {
            margin: 0;
        }

        .block {
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 6px 0;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #000000;
            font-weight: normal;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
            width: 110px;
        }

        .totals {
            margin-top: 8px;
        }

        .totals td {
            border-bottom: none;
            padding: 2px 0;
        }
    </style>
</head>
<body>
    @if ($billName)
        <h1>{{ $billName }}</h1>
    @endif

    <div class="block">
        @if ($providerName !== '')
            <p>{{ $providerName }}</p>
        @endif
        @foreach ($addressLines as $line)
            <p>{{ $line }}</p>
        @endforeach
    </div>

    @if ($date !== '')
        <div class="block">
            <p>Date</p>
            <p>{{ $date }}</p>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="amount">€{{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        @if ($discount > 0)
            <tr>
                <td>Subtotal</td>
                <td class="amount">€{{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Discount</td>
                <td class="amount">€{{ number_format($discount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>Total</td>
            <td class="amount">€{{ number_format($total, 2) }}</td>
        </tr>
    </table>
</body>
</html>
