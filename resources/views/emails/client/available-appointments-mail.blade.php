<!DOCTYPE html>
<html>
<body>
    <p>Dear {{ $file->patient->client->company_name }} Team,</p>
    <p>We have found available appointments for your case.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>Requested Service:</strong> {{ $file->serviceType->name }}</p>
    <p><strong>Service City:</strong> {{ $file->city->name ?? 'N/A' }}</p>
    <p>Here are the available slots:</p>
    <ul>
    @foreach($file->appointments->where('status', 'Available') as $appointment)
    <li>{{ $appointment->service_date }} at {{ $appointment->service_time }} {{$appointment->providerBranch->weekend_night_cost ? 'Average Cost: ' . $appointment->providerBranch->weekend_night_cost : ''}}</li>
    @endforeach
    </ul>
    <p>Please confirm your patient's preferred slot.</p>
    <p>Please note that a Telemedicine appointment is available for 25€.</p>

    @if($file->gop?->where('type', 'In')->first() && $file->gop?->where('type', 'Out')->first() && $file->gop?->where('type', 'In')->first()->amount < $file->gop?->where('type', 'Out')->first()->amount)
    <p>Please send us an updaed GOP with the ammount {{$file->gop->amount}} and the patient's preferred slot.</p>
    @endif

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>