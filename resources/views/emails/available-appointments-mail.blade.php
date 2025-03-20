<!DOCTYPE html>
<html>
<head>
    <title>Available Appointments for Case {{$data->mga_reference}} </title>
</head>
<body>
    <p>Dear {{ $data->patient->client->company_name }} Team,</p>
    <p>We have found available appointments for your case.</p>
    <p><strong>MGA Reference:</strong> {{ $data->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $data->patient->name }}</p>
    <p><strong>Requested Service:</strong> {{ $data->serviceType->name }}</p>
    <p><strong>Service City:</strong> {{ $data->city }}</p>
    <p>Here are the available slots:</p>
    <ul>
    @foreach($data->appointments->where('status', 'Pending') as $appointment)
    <li>{{ $appointment->service_date }} at {{ $appointment->service_time }}</li>
    @endforeach
    </ul>
    <p>Please confirm your patient's preferred slot.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>