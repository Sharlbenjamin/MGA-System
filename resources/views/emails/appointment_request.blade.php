<!DOCTYPE html>
<html>
<head>
    <title>Branch Appointment</title>
</head>
<body>
    <h2>Branch Appointment - {{ $file->patient->name }}</h2>
    
    <p>Hello,</p>
    
    @if($isCustomEmail)
        <p>You have received a branch appointment notification.</p>
    @else
        <p>You have received a branch appointment request for your branch: <strong>{{ $providerBranch->branch_name }}</strong>.</p>
    @endif
    
    <h3>File Details:</h3>
    <ul>
        <li><strong>MGA Reference:</strong> {{ $file->mga_reference }}</li>
        <li><strong>Patient:</strong> {{ $file->patient->name }}</li>
        <li><strong>Service Type:</strong> {{ $file->serviceType->name }}</li>
        <li><strong>Client:</strong> {{ $file->patient->client->company_name }}</li>
        @if($file->client_reference)
            <li><strong>Client Reference:</strong> {{ $file->client_reference }}</li>
        @endif
        @if($file->service_date)
            <li><strong>Requested Service Date:</strong> {{ $file->service_date }}</li>
        @endif
        @if($file->service_time)
            <li><strong>Requested Service Time:</strong> {{ $file->service_time }}</li>
        @endif
    </ul>

    @if($file->symptoms)
        <h3>Symptoms:</h3>
        <p>{{ $file->symptoms }}</p>
    @endif

    @if($file->diagnosis)
        <h3>Diagnosis:</h3>
        <p>{{ $file->diagnosis }}</p>
    @endif

    @if(!$isCustomEmail)
        <p>Please log in to your portal to confirm or reject this branch appointment.</p>
    @else
        <p>Please review this branch appointment request and take appropriate action.</p>
    @endif

    <p>Best regards,<br>
    MedGuard Assistance Team</p>
</body>
</html>