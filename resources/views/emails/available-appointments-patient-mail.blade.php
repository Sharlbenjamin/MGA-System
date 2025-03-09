<!DOCTYPE html>
<html>
<head>
    <title>New FIle is Created {{$file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $file->patient->name }},</p>
    <p>We have found available appointments</p>
    <p>Here are the available appointments:</p>
    <ul>
    @foreach($appointments as $appointment)
    <li>{{ $appointment->service_date }} at {{ $appointment->service_time }} - {{ $appointment->providerBranch->branch_name }}</li>
    @endforeach
    </ul>
    

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>