<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$appointment->file->mga_reference}}</title>
</head>
<body>
    <p>{{$appointment->file->patient->client->company_name}}</p>
    <p>We would like to confirm our <strong>{{ $appointment->providerBranch->branch_name }}</strong>appointment, scheduled for <strong>{{ $appointment->service_date }}</strong> at <strong>{{ $appointment->service_time }}</strong>.</p>
    <p>Thank you.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>