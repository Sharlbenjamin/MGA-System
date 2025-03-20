<!DOCTYPE html>
<html>
<head>
    <title>New File Created</title>
</head>
<body>
    <p>A new file has been created with the following details:</p>
    <p><strong>MGA Reference:</strong> {{ $data->mga_reference }}</p>
    <p><strong>Service Type:</strong> {{ $data->serviceType->name }}</p>
    <p><strong>Patient Name:</strong> {{ $data->patient->name }}</p>
    <p><strong>Status:</strong> {{ $data->status }}</p>

    <p><strong>Instructions for Employee:</strong></p>
    <ul>
        <li>Confirm with the client that their file has been created successfully.</li>
        <li>Provide details on the next steps and expected processing time.</li>
        <li>Check if any further information is needed from the client.</li>
    </ul>

    <p>Please proceed with the necessary actions.</p>
    
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>