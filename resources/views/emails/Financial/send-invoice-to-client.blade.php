<!DOCTYPE html>
<html>
<head>
    <title>Invoice</title>
</head>
<body>
    {!! nl2br(e($emailBody)) !!}

    @php
        $user = auth()->user();
        $signature = $user ? $user->signature : null;
    @endphp
    
    @if($signature)
        @include('draftsignature', ['signature' => $signature])
    @endif
</body>
</html>

