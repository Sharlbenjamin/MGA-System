<!DOCTYPE html>
<html>
<head>
    <title>New GOP Request</title>
</head>
<body>
Dear Team,

A GOP has been cancelled for file reference: {{ $gop->file->mga_reference }}
<br>
Please review the cancelled GOP at your earliest convenience.
<br>
Please note that you have 24 hours to review the cancelled GOP. and notify us if you have any costs.
<br>

@include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>
