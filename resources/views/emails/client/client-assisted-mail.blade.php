<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $file->patient->client->company_name }} Team,</p>
    <p>We are pleased to inform you that the patient has been assisted.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>{{$file->patient->client->company_name}} Reference:</strong> {{ $file->client_reference }}</p>
    <p><strong>Status :</strong> {{ $file->status }}</p>
    <p>Once our invoice is issued, your financial team will receive an invoice from our financial team's email (mga.financial@medguarda.com)</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>