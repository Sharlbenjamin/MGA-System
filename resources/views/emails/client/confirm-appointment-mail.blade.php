<!DOCTYPE html>
<html>
<head>
    <title>Appointment Notification</title>
</head>
<body>
    <p>Please go ahead and confirm this appointment</p>
    <p>Here are the appointment details</p>
    <p>Branch: <strong>{{ $appointment->providerBranch->branch_name }}</strong></p>
    <p>Date: <strong>{{ $appointment->service_date }}</strong></p>
    <p>Time: <strong>{{ $appointment->service_time }}</strong></p>
    <p>Service: <strong>{{ $appointment->file->serviceType->name }}</strong></p>
    <p>Provider: <strong>{{ $appointment->providerBranch->provider->name }}</strong></p>
    <p>Provider Preferred Phone: <strong>{{ $appointment->providerBranch->primaryContact('Appointment')->preferred_contact ?? 'N/A' }}</strong></p>
    <p>Phone: <strong>{{ $appointment->providerBranch->primaryContact('Appointment')->phone_number ?? 'N/A' }}</strong></p>
    <p>Phone: <strong>{{ $appointment->providerBranch->primaryContact('Appointment')->second_phone ?? 'N/A' }}</strong></p>

    <p>Follow this scheme:</p>
    <p>introduce yourself and the company</p>
    <p><strong>my name is "name" and I am calling on behalf of "MedGuard Assistance"</strong></p>
    <p>Confirm that you are calling the correct provider and the correct branch</p>
    <p><strong>"Am I calling {{$appointment->providerBranch->provider->name}}? and is this {{$appointment->providerBranch->branch_name}}?"</strong></p>
    <p>If they confirm. Proceed to the next step</p>
    <p>Check for the old appointment details from the comment section of this case {{$appointment->file->mga_reference}}</p>
    <p><strong>I would like to confirm the appointment details. as our patient {{$appointment->file->patient->name}} would like to attend the appointment.</strong></p>
    <p><strong>  Do you need any furthur details?</strong></p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>