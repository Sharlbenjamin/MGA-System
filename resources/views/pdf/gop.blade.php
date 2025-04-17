<!DOCTYPE html>
<html>
<head>
    <title>Guarantee of Payment</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            color: #333;
        }

        .page-wrapper {
            position: relative;
            min-height: 100vh;
            background: white;
            padding: 20mm;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.08;
            z-index: 1;
            pointer-events: none;
            width: 100%;
            text-align: center;
        }

        .watermark img {
            width: 400px;
            height: auto;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #1c4587;
            padding-bottom: 15px;
        }

        .header img {
            width: 80px;
            height: auto;
        }

        .header h1 {
            flex: 1;
            text-align: center;
            font-size: 26px;
            color: #1c4587;
            letter-spacing: 1px;
            margin-right: 80px;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            color: #1c4587;
            font-size: 18px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .info-table th {
            width: 30%;
            text-align: left;
            padding: 8px 12px;
            background-color: #1c4587;
            color: white;
            font-weight: normal;
            font-size: 14px;
            border-right: 2px solid #ffffff;
        }

        .info-table td {
            padding: 8px 12px;
            color: #333;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
            font-weight: bold;
        }

        .terms {
            font-size: 13px;
            color: #444;
            margin-bottom: 15px;
            text-align: justify;
        }

        .highlight {
            color: #1c4587;
            font-weight: bold;
        }

        .footer {
            position: absolute;
            bottom: 25px;
            left: 20mm;
            right: 20mm;
            padding-top: 15px;
            border-top: 2px solid #1c4587;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .footer-item {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #555;
        }

        .footer-label {
            color: #1c4587;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="watermark">
            <img src="{{ public_path('siglogo.png') }}" alt="Watermark">
        </div>

        <div class="header">
            <img src="{{ public_path('siglogo.png') }}" alt="Medical Logo">
            <h1>Guarantee of Payment</h1>
        </div>

        <div class="section">
            <h2 class="section-title">Patient Information</h2>
            <table class="info-table">
                <tr>
                    <th>MGA Reference</th>
                    <td>{{ $gop->file->mga_reference ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Patient Name</th>
                    <td>{{ $gop->file->patient->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Date of Birth</th>
                    <td>{{ $gop->file->patient->dob ? $gop->file->patient->dob->format('d/m/Y') : 'N/A' }}
                        {{ $gop->file->patient->dob ? '(age: '. intval($gop->file->patient->dob->diffInYears(\Carbon\Carbon::now())) . ' years)' : '' }}</td>
                </tr>
                <tr>
                    <th>Gender</th>
                    <td>{{ $gop->file->patient->gender ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Service Type</th>
                    <td>{{ $gop->file->serviceType->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Service Date & Time</th>
                    <td>{{ $gop->file->service_date ? $gop->file->service_date->format('d/m/Y') : 'N/A' }} at
                        {{ $gop->file->service_time ? \Carbon\Carbon::parse($gop->file->service_time)->format('H:i') : 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Address</th>
                    <td>{{ $gop->file->address ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Symptoms</th>
                    <td>{{ $gop->file->symptoms ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">Terms and Conditions</h2>
            <p class="terms">
                This Guarantee of Payment serves to formally notify you,
                <span class="highlight">{{ $gop->file->providerBranch?->name }}</span>, that the patient identified herein,
                whose personal and company details are also listed, requires medical attention. We hereby guarantee coverage of
                all expenses deemed medically necessary as emergency requirements, provided they do not exceed the stipulated
                amount of <span class="highlight">{{ $gop->amount }}€</span>.
            </p>
            <p class="terms">
                Furthermore, we request a cashless service for our patient, ensuring that no payments
                are collected unless explicitly pre-approved by our operations department.
            </p>
            <p class="terms">
                Finally, all invoices should be directed to "mga.providers.bills@medguarda.com".
            </p>
        </div>

        <div class="footer">
            <div class="footer-item">
                <span class="footer-label">Operation:</span>
                mga.operation@medguarda.com
            </div>
            <div class="footer-item">
                <span class="footer-label">Website:</span>
                medguarda.com
            </div>
            <div class="footer-item">
                <span class="footer-label">Phone:</span>
                +34 637 030 722
            </div>
            <div class="footer-item">
                <span class="footer-label">Provider Billing:</span>
                mga.providers.bills@medguarda.com
            </div>
        </div>
    </div>
</body>
</html>
