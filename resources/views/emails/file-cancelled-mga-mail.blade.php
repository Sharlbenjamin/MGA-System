<!DOCTYPE html>
<html>
<head>
    <title>File Cancellation Notification</title>
</head>
<body>
    <p>The following file has been cancelled:</p>
    <p><strong>MGA Reference:</strong> {{ $data->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $data->patient->name }}</p>
    <p><strong>Cancellation Reason:</strong> Not specified</p>

    <p><strong>Instructions for Employee:</strong></p>
    <ul>
        <li>Inform the client that the file has been cancelled.</li>
        <li>If the client asks for the reason, explain the specified cancellation reason.</li>
        <li>Advise the client on any next steps they need to take.</li>
    </ul>

    <p>If you need further assistance, please follow up accordingly.</p>
    
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>