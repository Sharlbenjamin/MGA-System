<!DOCTYPE html>
<html>
<body>
    <p>Dear {{ $file->patient->name }},</p>
    <p>We have found available appointments for your case.</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>Requested Service:</strong> {{ $file->serviceType->name }}</p>
    <p><strong>Service City:</strong> {{ $file->city->name ?? 'N/A' }}</p>
    <p>Here are the available slots:</p>
    <ul>
    @foreach($file->appointments->where('status', 'Available') as $appointment)
    <li>{{ $appointment->service_date }} at {{ $appointment->service_time }} {{$appointment->providerBranch->weekend_night_cost ? 'Average Cost: ' . $appointment->providerBranch->weekend_night_cost : ''}}</li>
    @endforeach
    </ul>
    <p>Please confirm your preferred slot.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>