<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $file->patient->name }},</p>
    <p>Your appointment has been confirmed.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ $file->appointments->where('status', 'Confirmed')->first()->service_date }}</li>
    <li><strong>Time:</strong> {{ $file->appointments->where('status', 'Confirmed')->first()->service_time }}</li>
    <li><strong>Location:</strong> {{ $file->appointments->where('status', 'Confirmed')->first()->providerBranch->branch_name }} - {{ $file->appointments->where('status', 'Confirmed')->first()->providerBranch->address }}</li>
    </ul>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>