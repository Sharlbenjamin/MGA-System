<!DOCTYPE html>
<html>
<body>
    <p>Dear Team,</p>
    <p>We are requesting a GOP for the following file.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>{{$file->patient->client->company_name}} Reference:</strong> {{ $file->client_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>Patient Date of Birth:</strong> {{ $file->patient->dob ?? "NA" }}</p>
    <p><strong>Patient Gender:</strong> {{ $file->patient->gender ?? "NA"}}</p>
    <p><strong>Patient Service Country:</strong> {{ $file->country->name ?? "NA"}}</p>
    <p><strong>Patient Service City:</strong> {{ $file->city->name ?? "NA"}}</p>
    <p><strong>Requested Service:</strong> {{ $file->serviceType->name ?? "NA"}}</p>
    <p>Status: <strong>{{$file->status}}</strong></p>
    @if(!empty($file->symptoms))
    <p><strong>Symptoms:</strong> {{ $file->symptoms }}</p>
    @endif
    <p>Please note that the expected cost for the appointment is <strong>{{$file->gops->where('type', 'In')->first()->amount}}</strong>, excluding our file fees.</p>
    <p>So, please send us a GOP as soon as possible, to confirm the {{$file->serviceType->name}} appointment.</p>
    <p>We remain at your disposal for any further information.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>