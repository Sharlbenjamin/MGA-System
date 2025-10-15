<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Medical Report</title>
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
        .diagnosis-info,
        .examination-info,
        .advice-info {
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
        .diagnosis-info p,
        .examination-info p,
        .advice-info p {
            margin: 5px 0;
            line-height: 1.3;
        }

        /* Vital Signs Section */
        .vital-signs {
            margin: 15px 0;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }

        .vital-signs p {
            display: inline-block;
            margin: 0 20px 0 0;
        }

        .vital-signs p:last-child {
            margin-right: 0;
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
                <h1>MEDICAL REPORT</h1>
            </div>

            <div class="doctor-info">
                <p>
                    <span class="bold">Doctor: </span>
                    <span class="data">{{ $customDoctorName ?? ($medicalReport->file->providerBranch?->provider?->name ?? 'N/A') }}</span>
                    <span style="float: right;">
                        <span class="bold">Date: </span>
                        <span class="data">{{ $medicalReport->date?->format('d/m/Y') ?? now()->format('d/m/Y') }}</span>
                    </span>
                </p>
            </div>

            <div class="section">
                <div class="patient-info">
                    <p><span class="bold">Patient Name: </span><span class="data">{{ $medicalReport->file->patient->name }}</span></p>
                    <p><span class="bold">Gender: </span><span class="data">{{ $medicalReport->file->patient->gender }}</span></p>
                    <p><span class="bold">Date of Birth: </span><span class="data">{{ $medicalReport->file->patient->dob?->format('d/m/Y') }}</span></p>
                </div>

                @if($medicalReport->complain)
                    <div class="diagnosis-info">
                        <p class="bold">Chief Complaint: </p>
                        <span class="data">{{ $medicalReport->complain }}</span>
                    </div>
                @endif

                @if($medicalReport->diagnosis)
                    <div class="diagnosis-info">
                        <p class="bold">Diagnosis: </p>
                        <span class="data">{{ $medicalReport->diagnosis }}</span>
                    </div>
                @endif

                @if($medicalReport->temperature || $medicalReport->blood_pressure || $medicalReport->pulse)
                    <div class="vital-signs">
                        @if($medicalReport->temperature)
                            <p><span class="bold">Temperature: </span><span class="data">{{ $medicalReport->temperature }}</span></p>
                        @endif
                        @if($medicalReport->blood_pressure)
                            <p><span class="bold">Blood Pressure: </span><span class="data">{{ $medicalReport->blood_pressure }}</span></p>
                        @endif
                        @if($medicalReport->pulse)
                            <p><span class="bold">Pulse: </span><span class="data">{{ $medicalReport->pulse }} bpm</span></p>
                        @endif
                    </div>
                @endif

                @if($medicalReport->history)
                    <div class="advice-info">
                        <p class="bold">History: </p>
                        <span class="data">{{ $medicalReport->history }}</span>
                    </div>
                @endif
                @if($medicalReport->examination)
                    <div class="advice-info">
                        <p class="bold">Examination: </p>
                        <span class="data">{{ $medicalReport->examination }}</span>
                    </div>
                @endif
                
                @if($medicalReport->advice)
                    <div class="advice-info">
                        <p class="bold">Medical Advice: </p>
                        <span class="data">{{ $medicalReport->advice }}</span>
                    </div>
                @endif
            </div>
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
