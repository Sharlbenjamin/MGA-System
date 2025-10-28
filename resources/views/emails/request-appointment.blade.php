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
        
        <div class="appointment-details">
            <div class="detail-line"><strong>MGA Reference:</strong> {{ $file->mga_reference }}</div>
            <div class="detail-line"><strong>Patient Name:</strong> {{ $file->patient->name }}</div>
            <div class="detail-line"><strong>Date of Birth:</strong> {{ $file->patient->dob ? \Carbon\Carbon::parse($file->patient->dob)->format('d-m-Y') : 'N/A' }}</div>
            <div class="detail-line"><strong>Gender:</strong> {{ $file->patient->gender }}</div>
            @if($file->serviceType->id == 1)
                <div class="detail-line"><strong>Address:</strong> {{ $file->address }}</div>
                <div class="detail-line"><strong>Phone:</strong> {{ $file->phone }}</div>
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

        <p>If the requested appointment is not availble, please let us know.</p>

        @include('draftsignature', ['signature' => auth()->user()->signature])
    </div>
</body>
</html>
