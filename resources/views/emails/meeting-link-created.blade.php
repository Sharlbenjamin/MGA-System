<!DOCTYPE html>
<html>
<head>
    <title>Telemedicine Meeting Link Created</title>
</head>
<body>
    <h1>Telemedicine Meeting Link Created</h1>
    <br>
    A new telemedicine meeting has been scheduled for:
    <br>
    <ul>
        <li><strong>File Reference:</strong> {{ $file->mga_reference }}</li>
        <li><strong>Patient Name:</strong> {{ $file->patient->name }}</li>
        <li><strong>Provider:</strong> {{ $file->providerBranch->branch_name ?? 'N/A' }}</li>
        <li><strong>Date:</strong> {{ $file->service_date->format('d M Y') }}</li>
        <li><strong>Time:</strong> {{ $file->service_time }}</li>
        <li><strong>Symptoms:</strong> {{ $file->symptoms ?? 'N/A' }}</li>
        <li><strong>Duration:</strong> 30 minutes</li>
    </ul>

    <p><strong>Meeting Link:</strong></p>
    @component('mail::button', ['url' => $meetLink])
    Join Meeting
    @endcomponent

    <p><strong>Important Notes:</strong></p>
    <ul>
        <li>Please ensure you have access to a stable internet connection</li>
        <li>Make sure your camera and microphone are working properly</li>
        <li>Join the meeting 5 minutes before the scheduled time</li>
        <li>Have your medical documents ready if needed</li>
    </ul>

    <p>If you have any questions or need to reschedule, please contact us immediately.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>
