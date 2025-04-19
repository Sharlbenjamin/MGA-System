<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prescription</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            min-height: 297mm;
            width: 210mm;
        }

        .page-wrapper {
            position: relative;
            min-height: 297mm;
            padding: 20mm;
            box-sizing: border-box;
        }

        .container {
            position: relative;
            background: transparent;
            padding-bottom: 150px;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
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

        /* Info Sections */
        .doctor-info,
        .patient-info,
        .diagnosis-info {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            background: transparent;
            margin-bottom: 15px;
        }

        .section {
            margin-bottom: 15px;
        }

        /* Update paragraph spacing */
        .doctor-info p,
        .patient-info p,
        .diagnosis-info p {
            margin: 5px 0;
            line-height: 1.3;
        }

        /* RX Section */
        .rx-section {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }

        .rx-symbol {
            font-size: 50px;
            font-weight: bold;
            color: #191970;
            font-family: 'Times New Roman', Times, serif;
            font-style: italic;
            transform: skew(-15deg);
            margin-right: 20px;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #253551;
            color: white;
        }

        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.15;
            z-index: -1;
            pointer-events: none;
            text-align: center;
            width: 100%;
        }

        .watermark img {
            width: 500px;
            height: auto;
        }

        /* Footer */
        .footer {
            position: absolute;
            bottom: 10mm;
            left: 0;
            width: 100%;
            text-align: left;
            font-size: 14px;
            color: #555;
            padding-top: 20px;
            background: rgba(255, 255, 255, 0.95);
            z-index: 2;
        }

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0 auto;
            width: 80%;
            border-top: 2px solid #191970;
            padding-top: 15px;
        }

        .footer ul li {
            margin: 5px 0;
        }

        /* Utility Classes */
        .bold {
            font-weight: bold;
            color: #191970;
        }

        .data {
            font-weight: bold;
            color: #01010b;
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
                <h1>PRESCRIPTION</h1>
            </div>

            <div class="doctor-info">
                <p>
                    <span class="bold">Doctor: </span>
                    <span class="data">{{ $prescription->file->providerBranch->provider->name }}</span>
                    <span style="float: right;">
                        <span class="bold">Date: </span>
                        <span class="data">{{ now()->format('d/m/Y') }}</span>
                    </span>
                </p>
            </div>

            <div class="section">
                <div class="patient-info">
                    <p><span class="bold">Patient Name: </span><span class="data">{{ $prescription->file->patient->name }}</span></p>
                    <p><span class="bold">Gender: </span><span class="data">{{ $prescription->file->patient->gender }}</span></p>
                    <p><span class="bold">Date of Birth: </span><span class="data">{{ $prescription->file->patient->dob?->format('d/m/Y') }}</span></p>
                </div>
                @php
                    if($prescription->file->medicalreports->first()) {
                        $diagnosis = $prescription->file->medicalreports->first()->diagnosis;
                    } elseif($prescription->file->diagnosis) {
                        $diagnosis = $prescription->file->diagnosis;
                    }else{
                        $diagnosis = null;
                    }
                @endphp
                @if($diagnosis)
                    <div class="diagnosis-info">
                        <p class="bold">Diagnosis: </p>
                        <span class="data">{{ $diagnosis }}</span>
                    </div>
                @endif
            </div>

            <div class="rx-section">
                <div class="rx-symbol">Rx</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Pharm.</th>
                        <th>Dosage</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($prescription->drugs as $drug)
                        <tr>
                            <td>{{ $drug->name }}</td>
                            <td>{{ $drug->pharmaceutical }}</td>
                            <td>{{ $drug->dose }}</td>
                            <td>{{ $drug->duration }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer">
            <ul>
                <li><span class="bold">Company Name: </span>Med Guard Assistance</li>
                <li><span class="bold">Email: </span>mga.operation@medguarda.com</li>
                <li><span class="bold">Phone: </span>+34 634 070 722</li>
                <li><span class="bold">Website: </span><a href="https://medguarda.com">medguarda.com</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
