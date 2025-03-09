<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$appointment->file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $appointment->providerBranch->branch_name }},</p>
    <p>We would like to confirm the appointment with the following detials.</p>
    <p><strong>MGA Reference:</strong> {{ $appointment->file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $appointment->file->patient->name }}</p>
    <p><strong>Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ $appointment->service_date }}</li>
    <li><strong>Time:</strong> {{ $appointment->service_time }}</li>
    <li><strong>Location:</strong> {{ $appointment->providerBranch->firstContact()->address }}</li>
    </ul>
    <p>This is an appointment confirmation.</p>

    <p>Thank you.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>