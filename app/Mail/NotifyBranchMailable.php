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
            'appointment_created' => 'emails.new-appointment-branch-mail',
            'appointment_confirmed' => 'emails.confirm-appointment-branch-mail',
            'appointment_updated' => 'emails.update-appointment-branch-mail',
            'appointment_cancelled' => 'emails.cancel-appointment-branch-mail',
            default => 'emails.general-notification-branch-mail',
        };

        $header = match ($this->type) {
            'appointment_created' => 'New Appointment Notification',
            'appointment_confirmed' => 'Appointment Confirmation',
            'appointment_updated' => 'Appointment Update',
            'appointment_cancelled' => 'Appointment Cancellation',
            default => 'General Notification', // Fallback
        };
        
        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($header)
                    ->with(['appointment' => $this->appointment]);
    }

}
