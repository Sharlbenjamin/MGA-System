<!DOCTYPE html>
<html>
<head>
    <title>New FIle is Created {{$file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $file->patient->client->company_name }} Team,</p>
<p>Your case has been successfully added to our files records.</p>
<p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
<p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
<p><strong>Patient Date of Birth:</strong> {{ $file->patient->dob ?? "NA" }}</p>
<p><strong>Patient Gender:</strong> {{ $file->patient->gender ?? "NA"}}</p>
<p><strong>Patient Service Country:</strong> {{ $file->country->name ?? "NA"}}</p>
<p><strong>Patient Service City:</strong> {{ $file->city->name ?? "NA"}}</p>
<p><strong>Requested Service:</strong> {{ $file->serviceType->name ?? "NA"}}</p>
@if(!empty($file->client_reference))
<p><strong>Client Reference:</strong> {{ $file->client_reference }}</p>
@endif
@if(!empty($file->symptoms))
<p><strong>Symptoms:</strong> {{ $file->symptoms }}</p>
@endif
<p>Status: <strong>{{$file->status}}</strong></p>
<p>Thank you for choosing our services.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>