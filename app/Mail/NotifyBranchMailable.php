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


class NotifyBranchMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $type;
    public $appointment;

    /**
     * Create a new message instance.
     */
    public function __construct($type, Appointment $appointment)
    {
        $this->type = $type;
        $this->appointment = $appointment;
    }
    
    public function build()
    {
        // Override only MAIL_USERNAME and MAIL_PASSWORD dynamically
        $username = Auth::user()->smtp_username;
        $password = Auth::user()->smtp_password;
        
        $view = match ($this->type) {
            'new' => 'emails.new-appointment-branch-mail',
            'confirm_appointment' => 'emails.confirm-appointment-branch-mail',
            'update' => 'emails.update-appointment-branch-mail',
            'cancel' => 'emails.cancel-appointment-branch-mail',
        };
        
        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject("Appointment Notification - {$this->appointment->file->mga_reference}")
                    ->with(['appointment' => $this->appointment]);
    }

}
