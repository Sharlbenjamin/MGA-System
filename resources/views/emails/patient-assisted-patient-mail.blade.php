<!DOCTYPE html>
<html>
<head>
    <title>Appointment Confirmation {{$file->mga_reference}}</title>
</head>
<body>
    <p>Dear {{ $file->patient->name }},</p>
    <p>We hope the appointment went well.</p>
    <p>If you have an inqueries. Please dont hesitate to inform us.</p>
    <p>Feel free to reply to this email.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>