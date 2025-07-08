<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DraftMail;

class FileDraftMailSeeder extends Seeder
{
    public function run()
    {
        $draftMails = [
            [
                'mail_name' => 'File Status Update - New',
                'body_mail' => "Dear {{client_name}},

We have received a new case for {{patient_name}} (MGA Reference: {{mga_reference}}).

Service Details:
- Service Type: {{service_type}}
- Location: {{city}}, {{country}}
- Address: {{address}}

{{#if diagnosis}}Diagnosis: {{diagnosis}}{{/if}}

We will keep you updated on the progress of this case.

Best regards,
MedGuard Team",
                'status' => 'New',
                'type' => 'File',
                'new_status' => 'Handling',
            ],
            [
                'mail_name' => 'Appointment Confirmation',
                'body_mail' => "Dear {{client_name}},

We are pleased to confirm the appointment for {{patient_name}} (MGA Reference: {{mga_reference}}).

{{#if appointment_details}}Appointment Details: {{appointment_details}}{{/if}}

{{#if cost_estimate}}Estimated Cost: {{cost_estimate}}{{/if}}

Please ensure the patient arrives 15 minutes before the scheduled time.

Best regards,
MedGuard Team",
                'status' => 'Confirmed',
                'type' => 'File',
                'new_status' => 'Confirmed',
            ],
            [
                'mail_name' => 'Requesting GOP (Guarantee of Payment)',
                'body_mail' => "Dear {{client_name}},

We are requesting a Guarantee of Payment (GOP) for {{patient_name}} (MGA Reference: {{mga_reference}}).

Service Details:
- Service Type: {{service_type}}
- Location: {{city}}, {{country}}
{{#if diagnosis}}- Diagnosis: {{diagnosis}}{{/if}}

{{#if cost_estimate}}Estimated Cost: {{cost_estimate}}{{/if}}

Please provide the GOP authorization as soon as possible to proceed with the appointment.

Best regards,
MedGuard Team",
                'status' => 'Requesting GOP',
                'type' => 'File',
                'new_status' => 'Requesting GOP',
            ],
            [
                'mail_name' => 'File Completed - Assisted',
                'body_mail' => "Dear {{client_name}},

We are pleased to inform you that the case for {{patient_name}} (MGA Reference: {{mga_reference}}) has been completed successfully.

Service Details:
- Service Type: {{service_type}}
- Location: {{city}}, {{country}}
{{#if diagnosis}}- Diagnosis: {{diagnosis}}{{/if}}

The patient has been assisted and the service has been completed. Any follow-up documentation will be provided separately.

Best regards,
MedGuard Team",
                'status' => 'Assisted',
                'type' => 'File',
                'new_status' => 'Assisted',
            ],
            [
                'mail_name' => 'File On Hold',
                'body_mail' => "Dear {{client_name}},

We need to inform you that the case for {{patient_name}} (MGA Reference: {{mga_reference}}) has been placed on hold.

Service Details:
- Service Type: {{service_type}}
- Location: {{city}}, {{country}}
{{#if diagnosis}}- Diagnosis: {{diagnosis}}{{/if}}

Reason for hold: Additional information or authorization required.

We will resume processing as soon as we receive the necessary information.

Best regards,
MedGuard Team",
                'status' => 'Hold',
                'type' => 'File',
                'new_status' => 'Hold',
            ],
        ];

        foreach ($draftMails as $draftMail) {
            DraftMail::updateOrCreate(
                ['mail_name' => $draftMail['mail_name']], // Avoid duplicate seeding
                $draftMail
            );
        }
    }
} 