<!DOCTYPE html>
<html>
<head>
    <title>Email Notification</title>
</head>
<body>
    <p>{!! nl2br(e($body)) !!}</p>

    @if(isset($message) && $message->embed(public_path('storage/' . auth()->user()->signature_image)))
        <br>
        <img src="{{ $message->embed(public_path('storage/' . auth()->user()->signature_image)) }}" 
             alt="Signature" width="300">
    @else
        <p>No signature available</p>
    @endif
</body>
</html>