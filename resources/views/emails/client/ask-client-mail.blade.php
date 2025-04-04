<!DOCTYPE html>
<html>
    <p>Dear {{ $file->patient->client->company_name }} Team,</p>
    <p>Your case has been successfully added to our files records.</p>
    <p>We kindly request your confirmation regarding who will be responsible for maintaining communication with the patient.</p>
    <p>We are waiting for your response to proceed with the next steps.</p>
    <p>Please find the details of the file below:</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p><strong>Patient Name:</strong> {{ $file->patient->name }}</p>
    <p><strong>Patient Date of Birth:</strong> {{ $file->patient->dob ?? "NA" }}</p>
    <p><strong>Patient Gender:</strong> {{ $file->patient->gender ?? "NA"}}</p>
    <p><strong>Patient Service Country:</strong> {{ $file->country->name ?? "NA"}}</p>
    <p><strong>Patient Service City:</strong> {{ $file->city->name ?? "NA"}}</p>
    <p><strong>Requested Service:</strong> {{ $file->serviceType->name ?? "NA"}}</p>
    <p>Status: <strong>{{$file->status}}</strong></p>
    @if(!empty($file->client_reference))
    <p><strong>Client Reference:</strong> {{ $file->client_reference }}</p>
    @endif
    @if(!empty($file->symptoms))
    <p><strong>Symptoms:</strong> {{ $file->symptoms }}</p>
    @endif

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>