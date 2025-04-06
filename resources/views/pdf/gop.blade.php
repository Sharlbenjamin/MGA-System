<!DOCTYPE html>
<html>
<head>
    <title>Prescription Report</title>
    <style>
        @page { size: A4 portrait; margin: 20px; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f9f9f9; }
        .container {
            width: 90%;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            min-height: calc(100vh - 40px); /* Account for container padding */
            position: relative;
            padding-bottom: 150px; /* Make space for footer */
        }
        .header { display: flex; align-items: center; justify-content: space-between; margin-top: 0; padding-top: 0; }
        .header img { width: 80px; height: auto; }
        .header h1 {
            flex: 1;
            text-align: center;
            font-size: 30px;
            text-transform: normal;
            color: #191970;
            margin: 0;
            letter-spacing: 2px;
            font-weight: bold;
        }
        h2 { color: #293559; margin: 20px 0; }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid #1c4587;
        }
        .info-table th {
            background-color: #1c4587;
            color: rgb(219, 219, 219);
            padding: 12px;
            text-align: left;
            width: 30%;
        }
        .info-table td {
            padding: 12px;
            font-weight: bold;
            border: 1px solid #191970;
            color: #191970;
        }
        .terms {
            font-size: 14px;
            color: #1c4587;
            margin-top: 20px;
        }
        .footer {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            text-align: center;
            font-size: 14px;
            color: #555;
            border-top: 2px solid #1c4587;
            padding-top: 15px;
        }
        .footer p {
            margin: 5px 0;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ public_path('siglogo.png') }}" alt="Medical Logo">
            <h1>Guarantee of Payment</h1>
        </div>

        <h2>Patient Information</h2>

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
                <td>{{ $gop->file->patient->dob ? $gop->file->patient->dob->format('d/m/Y') : 'N/A' }} {{ $gop->file->patient->dob ? '(age: '. intval($gop->file->patient->dob->diffInYears(\Carbon\Carbon::now())) . ' years)' : '' }}</td>
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
                <td>{{ $gop->file->service_date ? $gop->file->service_date->format('d/m/Y') : 'N/A' }} @
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

        <h2>Terms and Conditions</h2>

        <p class="terms">This Guarantee of Payment serves to formally notify you, <span style="font-weight: bold; color: #1c4587;">{{ $gop->file->providerBranch?->name }}</span>, that the patient identified herein,
            whose personal and company details are also listed, requires medical attention. We hereby guarantee coverage of
            all expenses deemed medically necessary as emergency requirements, provided they do not exceed the stipulated
            amount of <span style="font-weight: bold; color: #1c4587;">{{ $gop->amount }}€</span>. Furthermore, we request a cashless service for our patient, ensuring that no payments
            are collected unless explicitly pre-approved by our operations department.</p>
        <p class="terms">Finally, all invoices should be directed to "mga.providers.bills@medguarda.com".</p>

        <div class="footer">
            <p>Operation: <span style="font-weight: bold; color: #1c4587;">mga.operation@medguarda.com</span></p>
            <p>Website: <span style="font-weight: bold; color: #1c4587;">medguarda.com</span></p>
            <p>Phone :<span style="font-weight: bold; color: #1c4587;">+34 637 030 722</span></p>
            <p>Provider Billing :<span style="font-weight: bold; color: #1c4587;">mga.providers.bills@medguarda.com</span></p>
        </div>
    </div>
</body>
</html>
