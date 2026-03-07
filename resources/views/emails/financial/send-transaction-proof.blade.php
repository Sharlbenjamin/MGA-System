<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proof of Payment</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <p>Dear team,</p>

    <p>Find attached a proof of payment with the following details:</p>

    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <thead>
            <tr style="background: #f3f4f6;">
                <th style="border: 1px solid #d1d5db; text-align: left; padding: 8px;">Patient name</th>
                <th style="border: 1px solid #d1d5db; text-align: left; padding: 8px;">Our reference</th>
                <th style="border: 1px solid #d1d5db; text-align: left; padding: 8px;">Bill number</th>
                <th style="border: 1px solid #d1d5db; text-align: left; padding: 8px;">Bill date</th>
                <th style="border: 1px solid #d1d5db; text-align: right; padding: 8px;">Bill amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills as $bill)
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $bill->file?->patient?->name ?? '-' }}</td>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $bill->file?->mga_reference ?? '-' }}</td>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $bill->name ?? '-' }}</td>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $bill->bill_date?->format('d/m/Y') ?? '-' }}</td>
                    <td style="border: 1px solid #d1d5db; padding: 8px; text-align: right;">{{ number_format((float) $bill->total_amount, 2) }} EUR</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="border: 1px solid #d1d5db; padding: 8px;">No bill details available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p>Best Regards,</p>
    <p style="margin: 0;">Med Guard Assistance</p>
    @if($signature)
        @if(!empty($signature->job_title))
            <p style="margin: 0;">{{ $signature->job_title }}</p>
        @endif
        @if(!empty($signature->department))
            <p style="margin: 0;">{{ $signature->department }} Department</p>
        @endif
        @if(!empty($signature->work_phone))
            <p style="margin: 0;">Tel: {{ $signature->work_phone }}</p>
        @endif
    @endif
    <p style="margin: 0;">24/7 Email: mga.operation@medguarda.com</p>
    <p style="margin: 0;">Website: <a href="https://medguarda.com">medguarda.com</a></p>
</body>
</html>
