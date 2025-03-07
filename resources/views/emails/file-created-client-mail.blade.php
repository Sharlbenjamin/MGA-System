<!DOCTYPE html>
<html>
<head>
    <title>New FIle is Created {{$file->mga_reference}}</title>
</head>
<body>
    <p>Hello,</p>
    <p>Thank you.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>