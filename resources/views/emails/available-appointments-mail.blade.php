<!DOCTYPE html>
<html>
<head>
    <title>Available Appointments for Case {{$file->mga_reference}} </title>
</head>
<body>
    <p>Dear {{ $file->patient->client->company_name }} Team,</p>
    <p>We have found available appointments for your case.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>Requested Service:</strong> {{ $file->serviceType->name }}</p>
    <p><strong>Service City:</strong> {{ $file->city }}</p>
    <p>Here are the available slots:</p>
    <ul>
    @foreach($file->appointments->where('status', 'Pending') as $appointment)
    <li>{{ $appointment->service_date }} at {{ $appointment->service_time }}</li>
    @endforeach
    </ul>
    <p>Please confirm your patient's preferred slot.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>