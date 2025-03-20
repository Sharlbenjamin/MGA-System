<!DOCTYPE html>
<html>
<head>
    <title>File Assistance Completed</title>
</head>
<body>
    <p>The assistance process for the following file has been completed:</p>
    <p><strong>MGA Reference:</strong> {{ $data->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $data->patient->name }}</p>
    <p><strong>Assistance Details:</strong> {{ $data->assistance_details ?? 'No additional details' }}</p>

    <p><strong>Instructions for Employee:</strong></p>
    <ul>
        <li>Inform the client that the assistance process has been completed successfully.</li>
        <li>Provide any relevant details regarding the service rendered.</li>
        <li>Ask if they need any additional support or follow-up actions.</li>
    </ul>

    <p>Thank you for your support.</p>
    
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>