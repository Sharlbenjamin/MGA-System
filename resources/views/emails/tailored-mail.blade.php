<!DOCTYPE html>
<html>
<head>
</head>
<body>
    <p>{{ $body }}</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>