<!DOCTYPE html>
<html>
<body>
    <p>{{$the_message}}</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>