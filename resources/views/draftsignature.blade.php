<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draft Signature</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: left;
            padding: 30px; /* Increased from 25px */
            transform: scale(0.6); /* Increased from 0.5 to 0.6 (20% bigger) */
            transform-origin: top left; /* Ensures proper alignment */
        }
        .signature-container {
            display: flex;
            align-items: center;
            gap: 12px; /* Increased from 10px */
        }
        .signature-container img {
            width: 150px; /* Increased from 94px (20% bigger) */
            height: auto;
        }
        .vertical-line {
            width: 3px; /* Increased from 2px */
            background-color: #FFC107;
            height: 200px; /* Increased from 150px */
            margin-right: 2px;
            margin-left: 2px;
        }
        .signature-text {
            font-size: 11.1px; /* Increased from 10.6px */
            color: #2c3e50;
        }
        .name {
            font-size: 14.5px; /* Increased from 14px */
            font-weight: bold;
        }
        .company {
            font-size: 15.5px; /* Increased from 15px */
            font-weight: bold;
            color: #2c3e50;
        }
        .department {
            font-size: 14px; /* Increased from 11.1px (2px bigger) */
            font-weight: bold;
            color: #2c3e50;
        }
        .signature {
            font-family: 'Brush Script MT', cursive;
            font-size: 19.5px; /* Increased from 19px */
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 6px; /* Adjusted */
            margin-left: 6px; /* Adjusted */
        }
    </style>
</head>
<body>
    <div class="signature">
        Best Regards,
    </div>

    <div class="signature-container">
        <img src="{{ asset('storage/img/myimage.jpg') }}" alt="My Image">
        <div class="vertical-line"></div>
        <div class="signature-text">
            <span class="name">{{ $signature->name ?? '' }}</span><br><br>
            <span class="company">Med Guard Assistance</span><br><br>
            <span class="department">{{ $signature->job_title ?? '' }}</span><br>
            <span>{{ $signature->department ?? '' }} Department</span><br><br><br>
            Tel: {{ $signature->work_phone ?? '' }}<br>
            24/7 Email: mga.operation@medguarda.com<br>
            Website: <a href="https://medguarda.com" target="_blank">medguarda.com</a>
        </div>
    </div>

</body>
</html>