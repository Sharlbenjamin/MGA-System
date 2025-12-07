<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Patient Consent Form</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20mm;
            background: #fff;
            min-height: 297mm;
            width: 210mm;
            box-sizing: border-box;
        }

        .container {
            position: relative;
            background: transparent;
        }

        h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            color: #000;
        }

        .intro-text {
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 12px;
        }

        .authorization-text {
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 12px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 15px;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        table th {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 40%;
        }

        table td {
            width: 60%;
        }

        .signature-section {
            margin-top: 30px;
            line-height: 1.8;
            font-size: 12px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 200px;
            margin: 0 10px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            line-height: 1.6;
        }

        .footer-info {
            margin: 5px 0;
        }

        .bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Patient Consent Form</h1>

        <div class="intro-text">
            I, <span class="bold">{{ $file->patient->name ?? 'N/A' }}</span>, confirm that I have been informed of and understand the purpose of this form.
        </div>

        <div class="authorization-text">
            By signing below, I hereby authorize <span class="bold">Med Guard Assistance</span> to receive, obtain, and access my confidential medical and financial documents related to my visit to the medical facility mentioned below.
        </div>

        <div class="authorization-text">
            I authorize Med Guard Assistance to receive any medical documents related to my visit with the details below.
        </div>

        <div class="section-title">Medical Facility and Visit Details</div>

        <table>
            <tr>
                <th>Field</th>
                <th>Information</th>
            </tr>
            <tr>
                <td>Patient Name</td>
                <td>{{ $file->patient->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Date of Birth</td>
                <td>{{ $file->patient->dob ? $file->patient->dob->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Medical Facility Name</td>
                <td>{{ $file->providerBranch->branch_name ?? ($file->providerBranch->provider->name ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td>Dates of Visit/Treatment</td>
                <td>{{ $file->service_date ? $file->service_date->format('d/m/Y') : 'N/A' }}</td>
            </tr>
        </table>

        <div class="section-title">Authorization and Signature</div>

        <div class="authorization-text">
            I understand that the information released may be used by <span class="bold">Med Guard Assistance</span> for the purpose of processing claims, coordinating assistance, and related administrative tasks.
        </div>

        <div class="authorization-text">
            I understand that this authorization will remain in effect until the conclusion of the necessary administrative or claims processing activities related to the visit dates mentioned above, or until I revoke it in writing.
        </div>

        <div class="signature-section">
            <p>
                Signature of : <span class="bold">{{ $file->patient->name ?? 'N/A' }}</span><br>
                Date signed : <span class="bold">{{ now()->format('d/m/Y') }}</span><br>
                Signature: <span class="signature-line"></span>
            </p>
        </div>

        <div class="footer">
            <div class="footer-info"><span class="bold">Company Name:</span> Med Guard Assistance</div>
            <div class="footer-info"><span class="bold">Phone:</span> +34 634 070 722</div>
            <div class="footer-info"><span class="bold">Email:</span> mga.financial@medguarda.com</div>
            <div class="footer-info"><span class="bold">Website:</span> medguarda.com</div>
        </div>
    </div>
</body>
</html>

