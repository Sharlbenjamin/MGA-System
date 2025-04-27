<!DOCTYPE html>
<html>
<body>
    <p>Dear {{ $file->patient->client->company_name }} Team,</p>
    <p>Your Patient's appointment has been confirmed.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>{{$file->patient->client->company_name}} Reference:</strong> {{ $file->client_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ date('d-m-Y', strtotime($file->service_date)) }}</li>
    <li><strong>Time:</strong> {{ $file->service_time }}</li>
    <li><strong>Location:</strong> {{ $file->providerBranch?->primaryContact('Appointment')->address ?? 'N/A' }}</li>
    </ul>

    @if($file->gop?->where('type', 'In')->first() && $file->gop?->where('type', 'Out')->first() && $file->gop?->where('type', 'In')->first()->amount < $file->gop?->where('type', 'Out')->first()->amount)
    <p>Please send us an updaed GOP with the ammount {{$file->gop->amount}}.</p>
    @endif

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>