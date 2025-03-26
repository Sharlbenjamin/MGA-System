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

trait NotifiableEntity
{
    private function detectNotificationReason($data)
    {
        return match (get_class($data)) {
            \App\Models\Appointment::class => 'Appointment',
            \App\Models\File::class => 'File',
            default => 'General', // Fallback case
        };
    }

    public function sendNotification($reason, $status, $data, $parent = null)
    {
        $contact = $this->primaryContact($reason);
        if (!$contact) {
            Notification::make()->title("No Contact Found")->body("No {$reason} contact found")->danger()->send();
            return;
        }

        match ($contact->preferred_contact) {
            'Phone', 'Second Phone' => $this->notifyByPhone($data, $status),
            'Email', 'Second Email' => $this->notifyByEmail($reason, $status, $data, $parent),
            'First Whatsapp', 'Second Whatsapp' => $this->notifyByWhatsapp($data),
            'First SMS', 'Second SMS' => $this->notifyBySms($data),
        };
    }

    private function notifyByPhone($data, $status)
    {
        $file_id = $data instanceof \App\Models\File ? $data->id : ($data->file_id ?? null);

        if (!$file_id) {
            // Removed debugging log
        }

        // Removed debugging log

        // Step 1: Create Notification
        Notification::make()->title('Phone Notification')->body("Call the recipient")->send();

        // Step 2: Create a Task
        Task::create([
            'taskable_id' => $data->id,
            'taskable_type' => get_class($data),
            'department' => 'General',
            'title' => $status, // Use `$status` directly
            'description' => 'Call the recipient', // Fallback case
            'due_date' => now()->addMinutes(30),
            'user_id' => Auth::id(),
            'file_id' => $file_id,
        ]);

        // Step 3: Always Notify Us via Email
        Mail::to('mga.operation@medguarda.com')->send(new NotifyUsMailable($status, $data));
    }

    private function notifyByEmail($reason, $type, $data, $parent)
    {
        $mailable = match ($parent) {
            'Branch' => new NotifyBranchMailable($type, $data),
            'Client' => new NotifyClientMailable($type, $data),
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