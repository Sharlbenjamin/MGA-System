<!DOCTYPE html>
<html>
<head>
    <title>New GOP Request</title>
</head>
<body>
    <h1>Telemedicine Meeting Link Created</h1>
    <br>
    A new telemedicine meeting has been scheduled for:
    <br>
    <ul>
        <li>File Reference: {{ $file->mga_reference }}</li>
        <li>Patient Name: {{ $file->patient->name }}</li>
        <li>Date: {{ $file->service_date->format('d M Y') }}</li>
        <li>Time: {{ $file->service_time }}</li>
        <li>Symptoms: {{ $file->symptoms }}</li>
    </ul>

    @component('mail::button', ['url' => $meetLink])
    Join Meeting
    @endcomponent

    Please ensure you have access to a stable internet connection and a working camera/microphone before joining the meeting.
    @include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>
