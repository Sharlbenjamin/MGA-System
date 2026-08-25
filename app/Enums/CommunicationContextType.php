<?php

namespace App\Enums;

enum CommunicationContextType: string
{
    case AppointmentRequest = 'appointment_request';
    case MissingDocuments = 'missing_documents';
    case MissingBills = 'missing_bills';
    case OutstandingBillsAcknowledgement = 'outstanding_bills_acknowledgement';
    case TransactionNotification = 'transaction_notification';

    public function label(): string
    {
        return match ($this) {
            self::AppointmentRequest => 'Appointment Request',
            self::MissingDocuments => 'Missing Documents',
            self::MissingBills => 'Missing Bills',
            self::OutstandingBillsAcknowledgement => 'Outstanding Bills Update',
            self::TransactionNotification => 'Transaction Notification',
        };
    }

    public function defaultTemplateKey(): string
    {
        return $this->value;
    }
}
