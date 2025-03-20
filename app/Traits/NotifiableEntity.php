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

    public function sendNotification($reason, $status, $data)
    {
        $contact = $this->primaryContact($reason);
        if (!$contact) {
            Notification::make()->title("No Contact Found")->body("No {$reason} contact found")->danger()->send();
            return;
        }

        match ($contact->preferred_contact) {
            'Phone', 'Second Phone' => $this->notifyByPhone($data, $status),
            'Email', 'Second Email' => $this->notifyByEmail($reason, $status, $data),
            'first_whatsapp', 'second_whatsapp' => $this->notifyByWhatsapp($data),
            'SMS' => $this->notifyBySms($data),
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

    private function notifyByEmail($reason, $type, $data)
    {
        $mailable = match ($reason) {
            'Appointment' => new NotifyBranchMailable($type, $data), // Appointment expects (type, Appointment)
            'File' => new NotifyPatientMailable($type, $data), // File expects (type, File)
            'Invoice', 'Balance' => new NotifyClientMailable($type, $data), // Invoice expects (type, File)
        };

        Mail::to($this->primaryContact($reason)->email)->send($mailable);
    }

    public function notifyByWhatsapp($data)
    {
        // Future WhatsApp logic
    }

    public function notifyBySms($data)
    {
        // Future SMS logic
    }
}