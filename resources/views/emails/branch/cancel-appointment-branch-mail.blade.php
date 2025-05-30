<!DOCTYPE html>
<html>
<head>
    <title>Appointment Cancelation</title>
</head>
<body>
    <p>Dear {{ $appointment->providerBranch->branch_name }},</p>
    <p>The following appointment has been cancelled.</p>
    <p><strong>MGA Reference:</strong> {{ $appointment->file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $appointment->file->patient->name }}</p>
    <p><strong>Cancelled Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ date('d-m-Y', strtotime($appointment->service_date)) }}</li>
    <li><strong>Time:</strong> {{ $appointment->service_time }}</li>
    <li><strong>Location:</strong> {{ $appointment->providerBranch->primaryContact('Appointment')->address ?? 'N/A' }}</li>
    </ul>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>