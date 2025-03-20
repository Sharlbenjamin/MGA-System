<!DOCTYPE html>
<html>
<head>
    <title>File Handling Notification</title>
</head>
<body>
    <p>The following file is currently under handling:</p>
    <p><strong>MGA Reference:</strong> {{ $data->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $data->patient->name }}</p>
    <p><strong>Current Status:</strong> {{ $data->status }}</p>

    <p><strong>Instructions for Employee:</strong></p>
    <ul>
        <li>Inform the client that their file is currently being handled.</li>
        <li>Let them know if any additional documents or actions are required from their side.</li>
        <li>Provide an estimated time for further updates if available.</li>
    </ul>

    <p>Please continue the required actions.</p>
    
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>