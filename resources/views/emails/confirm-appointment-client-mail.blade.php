<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $file->patient->client->company_name }} Team,</p>
    <p>Your Patient's appointment has been confirmed.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>{{$file->patient->client->company_name}} Reference:</strong> {{ $file->client_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ $file->service_date }}</li>
    <li><strong>Time:</strong> {{ $file->service_time }}</li>
    <li><strong>Location:</strong> {{ $file->providerBranch->primaryContact('Appointment')->address ?? 'N/A' }}</li>
    </ul>
    
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>