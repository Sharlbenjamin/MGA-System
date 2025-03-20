<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use App\Models\Appointment;
use App\Models\File;

class NotifyClientMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $type;
    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }
    
    public function build()
    {
        // Ensure file relation is loaded before using it
        if (!isset($this->data->file) && method_exists($this->data, 'file')) {
            $this->data->load('file');
        }

        // Override only MAIL_USERNAME and MAIL_PASSWORD dynamically
        $username = Auth::user()->smtp_username;
        
        $view = match ($this->type) {
            'file_created' => 'emails.file-created-client-mail',
            'file_void' => 'emails.file-cancelled-mail',
            'file_hold' => 'emails.file-hold-client-mail',
            'client_confirm' => 'emails.confirm-appointment-client-mail',
            'file_available' => 'emails.available-appointments-mail',
            'reminder' => 'emails.reminder-appointment-mail',
            'assisted' => 'emails.patient-assisted-mail',
            'appointment_created' => 'emails.new-appointment-client-mail',
            'appointment_confirmed' => 'emails.confirm-appointment-client-mail',
            'appointment_cancelled' => 'emails.cancel-appointment-client-mail',
            'appointment_updated' => 'emails.update-appointment-client-mail',
            default => 'emails.general-notification-client-mail',
        };

        $header = match ($this->type) {
            'file_created' => 'File Created Notification',
            'file_void' => 'File Cancelled Notification',
            'client_confirm' => 'Appointment Confirmation',
            'file_handling' => 'Appointment Handling',
            'file_hold' => 'Appointment Hold',
            'file_available' => 'Available Appointments',
            'reminder' => 'Appointment Reminder',
            'assisted' => 'Patient Assisted',
            'appointment_created' => 'New Appointment Notification',
            'appointment_confirmed' => 'Appointment Confirmation',
            'appointment_cancelled' => 'Appointment Cancellation',
            'appointment_updated' => 'Appointment Update',
            default => 'General Notification',
        };
        
        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($header . " - " . ($this->data->file->mga_reference ?? 'No Reference'))
                    ->with(['file' => $this->data]);
    }

}
