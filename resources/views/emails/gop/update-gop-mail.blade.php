<!DOCTYPE html>
<html>
<head>
    <title>New GOP Request</title>
</head>
<body>
Dear Team,

A GOP has been updated for file reference: {{ $gop->file->mga_reference }}
<br>
Please review the updated GOP at your earliest convenience.
<br>
Please note that we can only cover the patient's costs up to the updated amount of GOP.
<br>

@include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>
