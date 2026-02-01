<!DOCTYPE html>
<html>
<head>
    <title>Appointment Request - {{ $file->mga_reference }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .content {
            background-color: #fff;
            padding: 20px;
        }
        .appointment-details {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #007cba;
        }
        .detail-line {
            margin: 8px 0;
        }
        .important-note {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            font-weight: bold;
        }
        .notes-section {
            margin: 15px 0 20px 0;
            padding: 16px;
            background-color: #fef2f2;
            border: 2px solid #dc2626;
            border-radius: 4px;
        }
        .notes-section .notes-heading {
            color: #b91c1c;
            font-weight: bold;
            font-size: 1.1em;
            margin: 0 0 10px 0;
        }
        .notes-section ul {
            margin: 0;
            padding-left: 22px;
        }
        .notes-section li {
            margin: 8px 0;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="content">
        <p>
            @if(isset($branch) && $branch)
                Dear {{ $branch->branch_name }},
            @else
                Dear Team,
            @endif
        </p>
        
        <p>We have a patient that needs a {{ $file->serviceType->name }} appointment. Find the details below:</p>

        <div class="notes-section">
            <p class="notes-heading">⚠ Important — Please read:</p>
            <ul>
                <li>The medical report (MR) and the invoice must be provided after the appointment.</li>
                <li>We only cover the initial consultation and the issuance of prescriptions; any additional procedures should be scheduled as a follow-up visit.</li>
            </ul>
        </div>
        
        <div class="appointment-details">
            <div class="detail-line"><strong>MGA Reference:</strong> {{ $file->mga_reference }}</div>
            <div class="detail-line"><strong>Patient Name:</strong> {{ $file->patient->name }}</div>
            <div class="detail-line"><strong>Date of Birth:</strong> {{ $file->patient->dob ? \Carbon\Carbon::parse($file->patient->dob)->format('d-m-Y') : 'N/A' }}</div>
            <div class="detail-line"><strong>Gender:</strong> {{ $file->patient->gender }}</div>
            @if($file->serviceType->id == 1)
                @if($file->address)
                <div class="detail-line"><strong>Address:</strong> {{ $file->address }}</div>
                @endif
                @if($file->phone)
                    <div class="detail-line"><strong>Phone:</strong> {{ $file->phone }}</div>
                @endif
            @endif
            
            <p style="margin-top: 15px; margin-bottom: 10px;"><strong>Appointment Details:</strong></p>
            
            <div class="detail-line"><strong>Date:</strong> {{ $file->service_date ? \Carbon\Carbon::parse($file->service_date)->format('d-m-Y') : 'N/A' }}</div>
            <div class="detail-line"><strong>Time:</strong> {{ $file->service_time ? \Carbon\Carbon::parse($file->service_time)->format('H:i') : 'N/A' }}</div>
            <div class="detail-line"><strong>Service:</strong> {{ $file->serviceType->name }}</div>
        </div>

        @if($file->symptoms)
        <div class="appointment-details">
            <p><strong>Symptoms:</strong></p>
            <p>{{ $file->symptoms }}</p>
        </div>
        @endif

        @if($file->gops->where('type', 'Out')->first())
        <div class="appointment-details">
            <p><strong>Coverage Amount:</strong></p>
            <p>{{ $file->gops->where('type', 'Out')->first()->amount }}€</p>
        </div>
        @endif

        <div class="important-note">
            <p><strong>Important:</strong> Please note that we will only cover the cost of the {{ $file->serviceType->name }} service mentioned above. If you need to perform any additional procedures or tests beyond this service, please contact us first for approval before proceeding. If the patient insisted on proceeding you will have to inform them that they may have to pay as it may or may not be covered by the insurance.</p>
        </div>

        <p>If the requested appointment is not availble, please let us know.</p>

        @include('draftsignature', ['signature' => auth()->user()->signature])
    </div>
</body>
</html>
