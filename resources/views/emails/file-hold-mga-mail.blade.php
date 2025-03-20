<!DOCTYPE html>
<html>
<head>
    <title>File Put on Hold</title>
</head>
<body>
    <p>The following file has been put on hold:</p>
    <p><strong>MGA Reference:</strong> {{ $data->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $data->patient->name }}</p>
    <p><strong>Reason for Hold:</strong> {{ $data->hold_reason ?? 'Not specified' }}</p>

    <p><strong>Instructions for Employee:</strong></p>
    <ul>
        <li>Inform the client that their file has been placed on hold.</li>
        <li>Explain the reason if specified and guide them on any required actions.</li>
        <li>Advise on the estimated timeline for file resolution if available.</li>
    </ul>

    <p>Please review the details and take necessary actions.</p>
    
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>