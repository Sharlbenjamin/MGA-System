<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$appointment->file->mga_reference}}</title>
</head>
<body>
    <h2>Appointment Confirmation - {{ $appointment->file->patient->name }}</h2>
    <p>Dear {{ $appointment->providerBranch->branch_name }},</p>
    <p>We would like to confirm the appointment with the following detials.</p>
    <p><strong>MGA Reference:</strong> {{ $appointment->file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $appointment->file->patient->name }}</p>
    <p><strong>Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ date('d-m-Y', strtotime($appointment->service_date)) }}</li>
    <li><strong>Time:</strong> {{ $appointment->service_time }}</li>
    @if($appointment->file->symptoms)
    <li><strong>Symptoms:</strong> {{ $appointment->file->symptoms }}</li>
    @endif
    </ul>
    <p><strong>This is an appointment confirmation.</strong></p>

    <p>Thank you.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>