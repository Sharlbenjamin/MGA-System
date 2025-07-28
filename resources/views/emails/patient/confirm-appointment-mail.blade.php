<!DOCTYPE html>
<html>
<body>
    <p>Dear {{ $file->patient->name ?? 'Patient' }},</p>
    <p>We are pleased to inform you that your {{$file->serviceType->name}} appointment has been confirmed.</p>
    <p>Here are the appointment details:</p>
    <p>Date: <strong>{{ date('d-m-Y', strtotime($file->service_date)) }}</strong></p>
    <p>Time: <strong>{{ $file->service_time }}</strong></p>
    <p>Service: <strong>{{ $file->serviceType->name }}</strong></p>
    <p>Branch: <strong>{{ $file->providerBranch->branch_name }}</strong></p>

    @if($file->symptoms)
    <p><strong>Symptoms:</strong> {{ $file->symptoms }}</p>
    @endif
    <p>Thank you for choosing our services.</p>
    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>