<!DOCTYPE html>
<html>
<head>
    <title>Appointment Request - {{ $file->mga_reference }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .highlight {
            background-color: #e7f3ff;
            padding: 10px;
            border-left: 4px solid #007cba;
            margin: 10px 0;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .details-table th,
        .details-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .details-table th {
            background-color: #f2f2f2;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Appointment Request - MedGuard Assistance</h2>
        <p><strong>Reference:</strong> {{ $file->mga_reference }}</p>
    </div>

    <div class="content">
        <p>Dear {{ $branch->branch_name }} Team,</p>
        
        <p>We hope this message finds you well. We are writing to request an appointment for one of our patients who requires medical attention.</p>
        
        <div class="highlight">
            <h3>Patient & Service Details</h3>
            <table class="details-table">
                <tr>
                    <th>MGA Reference</th>
                    <td>{{ $file->mga_reference }}</td>
                </tr>
                <tr>
                    <th>Patient Name</th>
                    <td>{{ $file->patient->name }}</td>
                </tr>
                <tr>
                    <th>Client</th>
                    <td>{{ $file->patient->client->company_name }}</td>
                </tr>
                <tr>
                    <th>Service Type</th>
                    <td>{{ $file->serviceType->name }}</td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td>{{ $file->service_date ? \Carbon\Carbon::parse($file->service_date)->format('F j, Y') : 'To be determined' }}</td>
                </tr>
                <tr>
                    <th>Time</th>
                    <td>{{ $file->service_time ? \Carbon\Carbon::parse($file->service_time)->format('g:i A') : 'To be determined' }}</td>
                </tr>
                <tr>
                    <th>Location</th>
                    <td>{{ $file->address }}</td>
                </tr>
                <tr>
                    <th>City</th>
                    <td>{{ $file->city->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Country</th>
                    <td>{{ $file->country->name ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        @if($file->symptoms)
        <div class="highlight">
            <h3>Symptoms</h3>
            <p>{{ $file->symptoms }}</p>
        </div>
        @endif

        <div class="highlight">
            <h3>Provider Branch Information</h3>
            <table class="details-table">
                <tr>
                    <th>Branch Name</th>
                    <td>{{ $branch->branch_name }}</td>
                </tr>
                <tr>
                    <th>Provider</th>
                    <td>{{ $branch->provider->name }}</td>
                </tr>
                <tr>
                    <th>Priority</th>
                    <td>{{ $branch->priority }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{{ $branch->status }}</td>
                </tr>
                @if($branch->phone)
                <tr>
                    <th>Phone</th>
                    <td>{{ $branch->phone }}</td>
                </tr>
                @endif
                @if($branch->email)
                <tr>
                    <th>Email</th>
                    <td>{{ $branch->email }}</td>
                </tr>
                @endif
                @if($branch->address)
                <tr>
                    <th>Address</th>
                    <td>{{ $branch->address }}</td>
                </tr>
                @endif
            </table>
        </div>

        <p><strong>Request:</strong> We would appreciate your assistance in scheduling an appointment for this patient. Please confirm availability and let us know any specific requirements or procedures we need to follow.</p>

        <p>If you have any questions or need additional information, please don't hesitate to contact us.</p>

        <p>Thank you for your time and cooperation.</p>

        <p>Best regards,<br>
        MedGuard Assistance Team</p>
    </div>

    <div class="footer">
        <p>This is an automated message from the MGA System. Please do not reply directly to this email.</p>
        <p>For urgent matters, please contact our operations team directly.</p>
    </div>
</body>
</html>
