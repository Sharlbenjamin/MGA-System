<!DOCTYPE html>
<html>
<head>
    <title>Invoice</title>
</head>
<body>
    {!! nl2br(e($emailBody)) !!}

    @include('draftsignature', ['signature' => auth()->user()->signature ?? ''])
</body>
</html>

