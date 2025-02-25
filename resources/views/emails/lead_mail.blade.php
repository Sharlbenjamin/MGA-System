<!DOCTYPE html>
<html>
<head>
    <title>Email Notification</title>
</head>
<body>
    <p>{!! nl2br($body) !!}</p>

    {{-- Include the draft signature --}}
    @include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>