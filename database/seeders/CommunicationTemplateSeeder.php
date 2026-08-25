<?php

namespace Database\Seeders;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationContextType;
use App\Models\CommunicationTemplate;
use Illuminate\Database\Seeder;

class CommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Appointment Request (WhatsApp)',
                'key' => CommunicationContextType::AppointmentRequest->value,
                'context_type' => CommunicationContextType::AppointmentRequest->value,
                'channel' => CommunicationChannel::WhatsApp->value,
                'subject_template' => null,
                'body_template' => <<<'TEXT'
Dear {{ provider_branch.name }},

We have a patient that needs a {{ appointment.service }} appointment. Please find the details below:

Important:
- The medical report (MR) and the invoice must be provided after the appointment.
- We only cover the initial consultation and the issuance of prescriptions; any additional procedures should be scheduled as a follow-up visit.

MGA Reference: {{ case.reference }}
Client Reference: {{ case.client_reference }}
Patient Name: {{ patient.name }}
Date of Birth: {{ patient.dob }}
Gender: {{ patient.gender }}
Patient Address: {{ patient.address }}
Phone: {{ patient.phone }}

Appointment Details:
Date: {{ appointment.date }}
Time: {{ appointment.time }}
Service: {{ appointment.service }}
Symptoms: {{ appointment.symptoms }}
Coverage Amount: {{ coverage.amount }}

Important: Please note that we will only cover the cost of the {{ appointment.service }} service mentioned above. If you need to perform any additional procedures or tests beyond this service, please contact us first for approval before proceeding.

If the requested appointment is not available, please let us know.

Kind regards,
{{ user.signature }}
MGA / MedGuard Assistance
TEXT,
            ],
            [
                'name' => 'Missing Documents (WhatsApp)',
                'key' => CommunicationContextType::MissingDocuments->value,
                'context_type' => CommunicationContextType::MissingDocuments->value,
                'channel' => CommunicationChannel::WhatsApp->value,
                'subject_template' => null,
                'body_template' => <<<'TEXT'
Dear {{ provider.name }} / Team,

Could you kindly send the outstanding documents for the following MGA cases?

{{ missing_files_table }}

Thank you for your assistance.

Kind regards,
{{ user.signature }}
MGA / MedGuard Assistance
TEXT,
            ],
            [
                'name' => 'Missing Bills (WhatsApp)',
                'key' => CommunicationContextType::MissingBills->value,
                'context_type' => CommunicationContextType::MissingBills->value,
                'channel' => CommunicationChannel::WhatsApp->value,
                'subject_template' => null,
                'body_template' => <<<'TEXT'
Dear {{ provider.name }} / Team,

Could you kindly provide the outstanding bills/invoices for the following MGA cases?

{{ cases_table }}

Thank you for your assistance.

Kind regards,
{{ user.signature }}
MGA / MedGuard Assistance
TEXT,
            ],
            [
                'name' => 'Outstanding Bills Update (WhatsApp)',
                'key' => CommunicationContextType::OutstandingBillsAcknowledgement->value,
                'context_type' => CommunicationContextType::OutstandingBillsAcknowledgement->value,
                'channel' => CommunicationChannel::WhatsApp->value,
                'subject_template' => null,
                'body_template' => <<<'TEXT'
Dear {{ provider.name }} / Team,

Please be informed that MGA has received the following bills/invoices and they are being processed:

{{ bills_table }}

If you have any questions, please contact us.

Kind regards,
{{ user.signature }}
MGA / MedGuard Assistance
TEXT,
            ],
            [
                'name' => 'Transaction Notification (WhatsApp)',
                'key' => CommunicationContextType::TransactionNotification->value,
                'context_type' => CommunicationContextType::TransactionNotification->value,
                'channel' => CommunicationChannel::WhatsApp->value,
                'subject_template' => null,
                'body_template' => <<<'TEXT'
Dear {{ provider.name }} / Team,

Please be informed that MGA has issued the following payment:

Transaction: {{ transaction.reference }}
Date: {{ transaction.date }}
Amount: {{ transaction.amount }} {{ transaction.currency }}

The payment relates to:

{{ transactions_table }}

Kind regards,
{{ user.signature }}
MGA / MedGuard Assistance
TEXT,
            ],
        ];

        foreach ($templates as $template) {
            CommunicationTemplate::query()->updateOrCreate(
                [
                    'key' => $template['key'],
                    'channel' => $template['channel'],
                ],
                $template + ['is_active' => true],
            );
        }
    }
}
