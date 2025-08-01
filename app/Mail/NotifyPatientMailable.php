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

class NotifyPatientMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $type;
    public $data; // Changed from $file to $data
    /**
     * Create a new message instance.
     */
    public function __construct($type, $data) // Updated parameter type
    {
        $this->type = $type;
        $this->data = $data; // Updated assignment
    }

    public function build()
    {
        // Ensure file relation is loaded before using it
        if (!isset($this->data->file) && method_exists($this->data, 'file')) {
            $this->data->load('file');
        }

        // Override only MAIL_USERNAME and MAIL_PASSWORD dynamically
        $username = Auth::user()->smtp_username;
        $password = Auth::user()->smtp_password;

        $view = match ($this->type) {
            // 'appointment_created' => 'emails.new-appointment-mga-mail',
            'appointment_confirmed' => 'emails.patient.confirm-appointment-mail',
            // 'appointment_updated' => 'emails.update-appointment-mga-mail',
            // 'appointment_cancelled' => 'emails.cancel-appointment-mga-mail',
            'file_created' => 'emails.patient.file-created-mail',
            'file_available' => 'emails.patient.file-available-mail',
            //'file_hold' => 'emails.file-hold-mga-mail',
            //'file_assisted' => 'emails.file-assisted-mga-mail',
        };

        if ($this->data instanceof File) {
            $file = $this->data;
        } else {
            $file = $this->data->file;
        }

        $subject = match ($this->type) {
            'appointment_confirmed' => 'Appointment Confirmed - ' . $file->patient->name . ' - ' . $file->mga_reference,
            'file_created' => 'File Created - ' . $file->patient->name . ' - ' . $file->mga_reference,
            'file_available' => 'Appointments Available - ' . $file->patient->name . ' - ' . $file->mga_reference,
        };

        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($subject)
                    ->with(['file' => $file]);
    }

}
