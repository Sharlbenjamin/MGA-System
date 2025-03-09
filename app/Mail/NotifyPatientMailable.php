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
    public $file;
    /**
     * Create a new message instance.
     */
    public function __construct($type, File $file)
    {
        $this->type = $type;
        $this->file = $file;
    }

    public function build()
    {
        // Override only MAIL_USERNAME and MAIL_PASSWORD dynamically
        $username = Auth::user()->smtp_username;
        $password = Auth::user()->smtp_password;
        
        $view = match ($this->type) {
            'patient_available' => 'emails.available-appointments-patient-mail', //done
            'confirm' => 'emails.confirm-appointment-patient-mail', // done
            'reminder' => 'emails.reminder-appointment-mail',
            'file_created' => 'emails.file-created-client-mail', //done
            'assisted' => 'emails.patient-assisted-patient-mail', //done
        };

        $header = match ($this->type) {
            'confirm' => 'Appointment Confirmation',
            'reminder' => 'Appointment Reminder',
            'patient_available' => 'Available Appointments',
            'file_created' => 'File Created Notification',
            'assisted' => 'Patient Assisted',
        };
        
        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($header)
                    ->with(['appointment' => $this->file]);
    }
    
}
