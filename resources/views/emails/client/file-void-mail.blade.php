<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $file->patient->client->company_name }},</p>
    <p>We regret to inform you that your case has been Deleted.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p>If you need further assistance, please reply to this email.</p>
    <p>Our financial department will be in contact incase there are any costs to be paid.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>