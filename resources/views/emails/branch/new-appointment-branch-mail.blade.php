<!DOCTYPE html>
<html>
<body>
    @if($appointment->providerBranch && $appointment->providerBranch->provider && $appointment->providerBranch->provider->status == 'Active')
    <p>Dear {{ $appointment->providerBranch->branch_name ?? 'Team' }},</p>
    <p>We are Requesting an appointment availability with the following details.</p>
    <p><strong>MGA Reference:</strong> {{ $appointment->file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $appointment->file->patient->name }}</p>
    <p><strong>Date of Birth:</strong> {{ $appointment->file->patient->dob ?? 'N/A' }}</p>
    @if($appointment->file->serviceType->id != 1)
    <p><strong>Patient Address:</strong> {{ $appointment->file->address ?? 'N/A' }}</p>
    @endif
    <p><strong>Appointment Details:</strong></p>
    <ul>
    <li><strong>Date:</strong> {{ date('d-m-Y', strtotime($appointment->service_date)) }}</li>
    <li><strong>Time:</strong> {{ $appointment->service_time ?? 'N/A' }}</li>
    <li><strong>Service:</strong> {{ $appointment->file->serviceType->name ?? '' }}</li>
    </ul>

    @if($appointment->file->symptoms)
    <p><strong>Symptoms:</strong> {{ $appointment->file->symptoms ?? 'N/A'}}</p>
    @endif

    @if($appointment->providerBranch->address)
    <p><strong>Branch Address:</strong> {{ $appointment->providerBranch->address }}</p>
    @endif

    @else

    <p>Dear team,</p>
    <p>We have a patient in {{ $appointment->file->city?->name ?? 'Your City' }} that needs a {{ $appointment->file->serviceType->name ?? 'Medical' }} appointment.</p>
    <p>Symptoms: {{ $appointment->file->symptoms ?? 'N/A' }}</p>
    <p>Can you provide our patient with the required medical attention?</p>
    <p>We are Med Guard Assistance, a Medical and Travel Assistance Company, That provides cashless medical services to our patients by paying to our providers on their behalf.</p>

    @endif

    <p>Please confirm the availability at your earliest convenience.</p>
    <p>This is not an appointment confirmation. this email is just for checking the availabiliity</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>
