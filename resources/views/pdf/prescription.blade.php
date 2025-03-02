<!DOCTYPE html>
<html>
<head>
    <title>Prescription Report</title>
    <style>
        @page { size: A4 portrait; margin: 20px; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f9f9f9; }
        .container { width: 90%; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); }
        .header { display: flex; align-items: center; justify-content: space-between; margin-top: 0; padding-top: 0; }
        .header img { width: 80px; height: auto; }
        .header h1 { flex: 1; text-align: center; font-size: 30px; text-transform: uppercase; color: #191970; margin: 0; }
        .doctor-info, .patient-info, .diagnosis-info { border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f4f4f4; margin-bottom: 10px; }
        .doctor-info { text-align: left; }
        .section { display: flex; justify-content: space-between; margin-top: 10px; flex-direction: column; }
        .bold { font-weight: bold; color: #191970; }
        .rx-section { display: flex; align-items: center; margin-top: 10px; }
        .rx-symbol { font-size: 50px; font-weight: bold; color: #191970; }
        .rx-details { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #253551; color: white; }
        .footer { margin-top: 20px; text-align: center; font-size: 14px; color: #555; }
        .signature { text-align: right; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ public_path('logo.png') }}" alt="Medical Logo">
            <h1>PRESCRIPTION</h1>
        </div>
        <div class="doctor-info">
            <p class="bold">Doctor:</p> {{ $prescription->file->provider->name }}
        </div>
        <div class="section">
            <div class="patient-info">
                <p class="bold">Patient Name:</p> {{ $prescription->file->patient->name }}
                <p class="bold">Gender:</p> {{ $prescription->file->patient->gender }}
                <p class="bold">Age:</p> {{ $prescription->file->patient->dob }}
            </div>
            <div class="diagnosis-info">
                <p class="bold">Diagnosis:</p> {{ $prescription->file->diagnosis }}
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
                    <th>Frequency</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                @foreach($prescription->drugs as $drug)
                    <tr>
                        <td>{{ $drug->name }}</td>
                        <td>{{ $drug->pharmaceutical }}</td>
                        <td>{{ $drug->dose }}</td>
                        <td>{{ $drug->frequency }}</td>
                        <td>{{ $drug->duration }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="footer">
            <p>📍 Company: Spain | 📞 Contact: +34634070722 | 🌍 Website: medguarda.com</p>
        </div>
    </div>
</body>
</html>
