<!DOCTYPE html>
<html>
<body>
    <p>{{$message}}</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>