<!DOCTYPE html>
<html>
<body>
    @if($appointment->providerBranch->provider->status == 'Active')
    <p>Dear {{ $appointment->providerBranch->branch_name }},</p>
    <p>We are Requesting an appointment availability with the following details.</p>
    <p><strong>MGA Reference:</strong> {{ $appointment->file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $appointment->file->patient->name }}</p>
    <p><strong>Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ date('d-m-Y', strtotime($appointment->service_date)) }}</li>
    <li><strong>Time:</strong> {{ $appointment->service_time }}</li>
    <li><strong>Location:</strong> {{ $appointment->providerBranch->primaryContact('Appointment')->address ?? 'N/A' }}</li>
    </ul>

    @else

    <p>Dear {{ $appointment->providerBranch->branch_name }},</p>
    <p>We are Med Guard Assistance, a Medical and Travel Assistance Company, That provides cashless medical services to our patients by paying to our providers on their behalf.</p>
    <p>We are currently in need of a {{ $appointment->file->serviceType->name }} appointment for a patient in {{$appointment->file->city->name}}.</p>
    <p>Symptoms: {{ $appointment->file->symptoms }}</p>
    <p>Can you provide our patient with the required medical attenction and we will pay you directly?</p>
    <p>If you confirm, we are going to send you our GOP (Guarantee of Payment) as a proof of payment.</p>


    @endif

    <p>Please confirm the availability at your earliest convenience.</p>
    <p>This is not an appointment confirmation. this email is just for checking the availabiliity</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>
