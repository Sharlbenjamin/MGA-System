<!DOCTYPE html>
<html>
<head>
    <title>New GOP Request</title>
</head>
<body>
Dear Team,

A new GOP has been submitted for file reference: {{ $gop->file->mga_reference }}
<br>
Please review the new GOP at your earliest convenience.
<br>
Please note that we can only cover the patient's costs up to the amount of GOP.
<br>

@include('draftsignature', ['signature' => auth()->user()->signature])

</body>
</html>
