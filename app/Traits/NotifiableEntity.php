<?php

namespace App\Traits;

use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use App\Mail\NotifyBranchMailable;
use App\Mail\NotifyPatientMailable;
use App\Mail\NotifyClientMailable;
use App\Mail\NotifyUsMailable;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait NotifiableEntity
{

    public function primaryContact($reason = null)
    {
        if ($reason === 'Invoice' || $reason === 'Balance' || $reason === 'Financial') {
            return $this->financialContact;
        } elseif ($reason === 'Appointment') {
            // Use operation contact for appointment notifications (without status filter for debugging)
            return $this->operationContact;
        } elseif ($reason === 'File' || $reason === 'Operation') {
            return $this->operationContact;
        } elseif ($reason === 'GOP') {
            return $this->gopContact;
        }

        return null;
    }

    private function detectNotificationReason($data)
    {
        return match (get_class($data)) {
            \App\Models\Appointment::class => 'Appointment',
            \App\Models\File::class => 'File',
            default => 'General', // Fallback case
        };
    }

    public function sendNotification($reason, $status, $data, $parent = null, $message = null)
    {
        $contact = $this->primaryContact($reason);
        if (!$contact) {
            Notification::make()->title("No ".$parent." Contact Found")->body("No {$reason} contact found")->danger()->send();
            return;
        }

        match ($contact->preferred_contact) {
            'Phone', 'Second Phone' => $this->notifyByPhone($data, $status),
            'Email', 'Second Email' => $this->notifyByEmail($reason, $status, $data, $parent, $message),
            'First Whatsapp', 'Second Whatsapp' => $this->notifyByWhatsapp($data),
            'First SMS', 'Second SMS' => $this->notifyBySms($data),
        };
    }

    private function notifyByPhone($data, $status)
    {
        $file_id = $data instanceof \App\Models\File ? $data->id : ($data->file_id ?? null);

        $recipient = "Patient or Branch or Client";
        Notification::make()->title('Phone Notification')->body("Call the" .$recipient)->send();


        Task::create([
            'taskable_id' => $data->id,
            'taskable_type' => get_class($data),
            'department' => 'Operation',
            'title' => 'Phone Call Required', // Use `$status` directly
            'description' => 'Call the recipient', // Fallback case
            'due_date' => now()->addMinutes(30),
            'user_id' => Auth::id(),
            'file_id' => $file_id,
        ]);

        // Internal notifications are now handled manually to prevent duplicate emails
        // Mail::to('mga.operation@medguarda.com')->send(new NotifyUsMailable($status, $data));
    }

    private function notifyByEmail($reason, $type, $data, $parent, $message = null)
    {
        // Client notifications are disabled as per new flow – DO NOT ENABLE
        if ($parent === 'Client' || $parent === 'Patient') {
            Log::info('Client/Patient notification skipped as per new flow', [
                'reason' => $reason,
                'type' => $type,
                'parent' => $parent
            ]);
            return;
        }

        $mailable = match ($parent) {
            'Branch' => new NotifyBranchMailable($type, $data),
            'Client' => new NotifyClientMailable($type, $data, $type === 'Custom' ? $message : null),
            'Patient' => new NotifyPatientMailable($type, $data),
        };

        Mail::to($this->primaryContact($reason)->email)->send($mailable);
    }

    public function notifyByWhatsapp($data)
    {
        Notification::make()->title('No whats app Reminder Yet')->danger()->send();
        return;
        $twilio = new \Twilio\Rest\Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $contact = $this->primaryContact($this->detectNotificationReason($data));

        // Clean and format the recipient's phone number
        $cleanNumber = preg_replace('/[^0-9+]/', '', $contact->phone_number);
        if (!str_starts_with($cleanNumber, '+')) {
            $cleanNumber = '+' . $cleanNumber;
        }

        // Get the from number without any formatting
        $fromNumber = config('services.twilio.whatsapp_from');
        // Remove any existing prefixes or formatting
        $fromNumber = preg_replace('/[^0-9]/', '', $fromNumber);

        $message = "Hello! You have a new notification regarding your " . $this->detectNotificationReason($data);

        try {
            $twilio->messages->create(
                "whatsapp:" . $cleanNumber,  // To number with whatsapp: prefix
                [
                    'from' => "whatsapp:+1" . $fromNumber,  // From number with proper US format
                    'body' => $message
                ]
            );
            Notification::make()->title('WhatsApp Notification')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('WhatsApp Notification Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function notifyBySms($data)
    {
        // Future SMS logic
    }
}
