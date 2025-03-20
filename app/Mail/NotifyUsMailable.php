<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class NotifyUsMailable extends Mailable
{
    use Queueable, SerializesModels;
    public $type;
    public $data;

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

        $view = match ($this->type) {
            'appointment_confirmed_client' => 'emails.client.confirm-appointment-mail',
            'appointment_confirmed_patient' => 'emails.patient.confirm-appointment-mail',
            'appointment_created' => 'emails.new-appointment-mga-mail',
            'appointment_confirmed' => 'emails.confirm-appointment-mga-mail',
            'appointment_updated' => 'emails.update-appointment-mga-mail',
            'appointment_cancelled' => 'emails.cancel-appointment-mga-mail',
            'file_created' => 'emails.file-created-mga-mail',
            'file_cancelled' => 'emails.file-cancelled-mga-mail',
            'file_hold' => 'emails.file-hold-mga-mail',
            'file_assisted' => 'emails.file-assisted-mga-mail',
            'file_handling' => 'emails.file-handling-mga-mail', // Added handling case
            'file_available' => 'emails.available-appointments-mail',
            default => 'emails.general-notification-mga-mail',
        };
        
        return $this->view($view)
                    ->from(Auth::user()->smtp_username, Auth::user()->name)
                    ->subject(ucwords(str_replace('_', ' ', $this->type)) . " - " . ($this->data->mga_reference))
                    ->with(['appointment' => $this->data]);
    }
}
