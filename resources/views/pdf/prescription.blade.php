<!DOCTYPE html>
<html>
<head>
    <title>{{ $prescription->file->patient->name }} Prescription Report {{ $prescription->file->mga_reference }}</title>
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
            min-height: 100vh;
            position: relative;
            padding-bottom: 150px; /* Space for footer */
        }
        .header { display: flex; align-items: center; justify-content: space-between; margin-top: 0; padding-top: 0; }
        .header img { width: 80px; height: auto; }
        .header h1 {
            flex: 1;
            text-align: center;
            font-size: 30px;
            text-transform: uppercase;
            color: #191970;
            margin: 0 0 20px 0; /* Added bottom margin */
        }
        .doctor-info, .patient-info, .diagnosis-info { border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f4f4f4; margin-bottom: 10px; }
        .doctor-info { text-align: left; }
        .section { display: flex; justify-content: space-between; margin-top: 10px; flex-direction: column; }
        .bold { font-weight: bold; color: #303074; display: inline; }
        .rx-section { display: flex; align-items: center; margin-top: 10px; }
        .rx-symbol {
            font-size: 50px;
            font-weight: bold;
            color: #191970;
            font-family: 'Times New Roman', Times, serif;
            font-style: italic;
            transform: skew(-15deg);  /* This adds extra slant to make it more italic */
        }
        .rx-details { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #253551; color: white; }
        .footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 14px;
            color: #555;
            border-top: 2px solid #191970;  /* Added horizontal line */
            padding-top: 20px;  /* Added space between line and content */
        }
        .signature { text-align: right; margin-top: 20px; }
        .data { font-weight: bold; color: #01010b; display: inline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ public_path('siglogo.png') }}" alt="Medical Logo">
            <h1>PRESCRIPTION</h1>
        </div>
        <div class="doctor-info">
            <p><span class="bold">Doctor: </span><span class="data">{{ $prescription->file->providerBranch->name }}</span></p>
        </div>
        <div class="section">
            <div class="patient-info">
                <p><span class="bold">Patient Name: </span><span class="data">{{ $prescription->file->patient->name }}</span></p>
                <p><span class="bold">Gender: </span><span class="data">{{ $prescription->file->patient->gender }}</span></p>
                <p><span class="bold">Date of Birth: </span><span class="data">{{ $prescription->file->patient->dob?->format('d/m/Y') }}</span></p>
            </div>
            <div class="diagnosis-info">
                <p class="bold">Diagnosis: </p> <span class="data">{{ $prescription->file->diagnosis }}</span>
            </div>
        </div>
        <div class="rx-section">
            <div class="rx-symbol">Rx</div>
            <div class="rx-details"></div>
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
        <div class="footer">
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
