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
    <li><strong>Service:</strong> {{ $appointment->serviceType->name }}</li>
    </ul>

    @if($appointment->file->symptoms)
    <p><strong>Symptoms:</strong> {{ $appointment->file->symptoms }}</p>
    @endif

    @else

    <p>Dear team,</p>
    <p>We have a patinet in {{ $appointment->file->city?->name }} that needs a {{ $appointment->file->serviceType->name }} appointment.</p>
    <p>Symptoms: {{ $appointment->file->symptoms }}</p>
    <p>Can you provide our patient with the required medical attention?</p>
    <p>We are Med Guard Assistance, a Medical and Travel Assistance Company, That provides cashless medical services to our patients by paying to our providers on their behalf.</p>


    @endif

    <p>Please confirm the availability at your earliest convenience.</p>
    <p>This is not an appointment confirmation. this email is just for checking the availabiliity</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>
