<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Models\Appointment;
use App\Models\File;

class NotifyClientMailable extends Mailable
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
            'file_created' => 'emails.file-created-client-mail',
            'file_void' => 'emails.file-cancelled-mail',
            'client_confirm' => 'emails.confirm-appointment-client-mail',
            'file_handling' => 'emails.handling-appointment-client-mail',
            'available' => 'emails.available-appointments-mail',
            'reminder' => 'emails.reminder-appointment-mail',
            'assisted' => 'emails.patient-assisted-mail',
        };
        
        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject("Appointment Notification - {$this->type}")
                    ->with(['file' => $this->file]);
    }

}
