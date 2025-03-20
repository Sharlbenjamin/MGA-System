<!DOCTYPE html>
<html>
<head>
    <title>Appointment Cancelation</title>
</head>
<body>
    <p>Dear {{ $appointment->providerBranch->branch_name }},</p>
    <p>An update has been made to an appointment.</p>
    <p>Please confirm the availability of the appointment datails</p>
    <p><strong>MGA Reference:</strong> {{ $appointment->file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $appointment->file->patient->name }}</p>
    <p><strong>Updated Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ $appointment->service_date }}</li>
    <li><strong>Time:</strong> {{ $appointment->service_time }}</li>
    <li><strong>Location:</strong> {{ $appointment->providerBranch->primaryContact('Appointment')->address ?? 'N/A' }}</li>
    </ul>
    <p>Please take note of these changes.</p>
    <p>Please confirm the availability at your earliest convenience.</p>
    <p>This is not an appointment confirmation. this email is just for checking the availabiliity</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>