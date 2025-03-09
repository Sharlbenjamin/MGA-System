<!DOCTYPE html>
<html>
<head>
    <title>Appointment Notification</title>
</head>
<body>
    <p>Please go ahead and cancel this appointment</p>
    <p>Here are the appointment details</p>
    <p>Branch: <strong>{{ $appointment->providerBranch->branch_name }}</strong></p>
    <p>Date: <strong>{{ $appointment->service_date }}</strong></p>
    <p>Time: <strong>{{ $appointment->service_time }}</strong></p>
    <p>Service: <strong>{{ $appointment->file->serviceType->name }}</strong></p>
    <p>Provider: <strong>{{ $appointment->providerBranch->provider->name }}</strong></p>
    <p>Provider Preferred Phone: <strong>{{ $appointment->providerBranch->firstContact()->preferred_contact }}</strong></p>
    <p>Phone: <strong>{{ $appointment->providerBranch->firstContact()->phone_number }}</strong></p>
    <p>Phone: <strong>{{ $appointment->providerBranch->firstContact()->second_phone }}</strong></p>
    <p>Follow this scheme: </p>
    <p>introduce yourself and the company</p>
    <p><strong>my name is "name" and I am calling on behalf of "MedGuard Assistance"</strong></p>
    <p>Confirm that you are calling the correct provider and the correct branch</p>
    <p><strong>"Am I calling {{$appointment->providerBranch->provider->name}}? and is this {{$appointment->providerBranch->branch_name}}?"</strong></p>
    <p>If they confirm. Proceed to the next step</p>
    <p>Check for the old appointment details from the comment section of this case {{$appointment->file->mga_reference}}</p>
    <p>I would like to upadte the date and time of the appointment to be {{$appointment->service_date}} at {{$appointment->service_time}}</p>
    <p><strong>I would like to go ahead and cancel this appointment for the time being. Thanks for understanding</strong></p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>