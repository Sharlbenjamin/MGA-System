<!DOCTYPE html>
<html>
<head>
    <title>Appointment Cancellation</title>
</head>
<body>
    <h2>Appointment Cancellation - {{ $appointment->file->patient->name }}</h2>
    <p>Dear {{ $appointment->providerBranch->branch_name }},</p>
    <p>The following appointment has been cancelled.</p>
    <p><strong>MGA Reference:</strong> {{ $appointment->file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $appointment->file->patient->name }}</p>
    <p><strong>Cancelled Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ date('d-m-Y', strtotime($appointment->service_date)) }}</li>
    <li><strong>Time:</strong> {{ $appointment->service_time }}</li>
    @if($appointment->file->symptoms)
    <li><strong>Symptoms:</strong> {{ $appointment->file->symptoms }}</li>
    @endif
    </ul>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>