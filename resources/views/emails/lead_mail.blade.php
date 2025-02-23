<!DOCTYPE html>
<html>
<head>
    <title>Email Notification</title>
</head>
<body>
    <p>{!! nl2br(e($body)) !!}</p>

    @if(isset($signature))
    <img src="{{ asset('storage/signatures/' . basename($signature)) }}" 
         alt="Signature" width="300" style="background: white; padding: 10px; border-radius: 5px;">
    @else
        <p>No signature available</p>
    @endif
</body>
</html>