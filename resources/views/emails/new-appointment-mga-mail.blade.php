<!DOCTYPE html>
<html>
<head>
    <title>Appointment Notification</title>
</head>
<body>
    <p>Hello,</p>
    <p>Your appointment at <strong>{{ $appointment->providerBranch->branch_name }}</strong> is scheduled for <strong>{{ $appointment->service_date }}</strong> at <strong>{{ $appointment->service_time }}</strong>.</p>
    <p>Thank you.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>