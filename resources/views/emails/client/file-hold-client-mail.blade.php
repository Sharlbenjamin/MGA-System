<!DOCTYPE html>
<html>
<body>
    <p>Dear {{ $file->patient->client->company_name }},</p>
    <p>We inform you that your case is on hold and all the appointments reserved or in hold for your patients have been cancelled.</p>
    <p><strong>MGA Reference:</strong> {{ $file->mga_reference }}</p>
    <p>If you need further assistance, please reply to this email.</p>

    @include('draftsignature', ['signature' => auth()->user()->signature])
</body>
</html>