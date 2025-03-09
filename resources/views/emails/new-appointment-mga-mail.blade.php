<!DOCTYPE html>
<html>
<head>
    <title>Appointment Notification</title>
</head>
<body>
    <p>Please go ahead and Ask for appointment without confirmation</p>
    <p>Here are the appointment details</p>
    <p>Branch: <strong>{{ $appointment->providerBranch->branch_name }}</strong></p>
    <p>Date: <strong>{{ $appointment->service_date }}</strong></p>
    <p>Time: <strong>{{ $appointment->service_time }}</strong></p>
    <p>Service: <strong>{{ $appointment->file->serviceType->name }}</strong></p>
    <p>Provider: <strong>{{ $appointment->providerBranch->provider->name }}</strong></p>
    <p>Provider Preferred Phone: <strong>{{ $appointment->providerBranch->firstContact()->preferred_contact }}</strong></p>
    <p>Phone: <strong>{{ $appointment->providerBranch->firstContact()->phone_number }}</strong></p>
    <p>Phone: <strong>{{ $appointment->providerBranch->firstContact()->second_phone }}</strong></p>
    <p>Follow this scheme:</p>
    <p>introduce yourself and the company</p>
    <p><strong>my name is "name" and I am calling on behalf of "MedGuard Assistance"</strong></p>
    <p>Confirm that you are calling the correct provider and the correct branch</p>
    <p><strong>"Am I calling {{$appointment->providerBranch->provider->name}}? and is this {{$appointment->providerBranch->branch_name}}?"</strong></p>
    <p>If they confirm. Proceed to the next step</p>
    <p><strong>I would like to check if you have an available appointment on {{$appointment->service_date}} at {{$appointment->service_time}}, as we have a patient with the following symotoms:</p>
    <p>{{$appointment->file->symptoms}} and in need of medical attention.</strong></p>
    <p>If they confirm having an appointment. Proceed to the next step.</p>
    <p><strong>I would like to go ahead and put this appointment on hold for the time being to check the patient's availability and feedback to you ASAP.</strong></p>
    <p>If they confirm or agree. Proceed</p>
    <p><strong>Do I need to save any appointment reference or data to be able to confirm or cancel this appointment in the future?</strong></p>
    <p>If yes, please go ahead and save the data in a comment relatied to the appointment in the system.</p>
    <p>Finally, say</p>
    <p><strong>"Please note that this is not an actual appointment yet. and that we may cancel in the future without any charge."</p>
    <p>"This phone call is just to check the availability of the appointment."</strong></p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>