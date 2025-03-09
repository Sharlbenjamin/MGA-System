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
    public $appointment;

    public function __construct($type, Appointment $appointment)
    {
        $this->type = $type;
        $this->appointment = $appointment;
    }
    public function build()
    {
        $username = Auth::user()->smtp_username;
        $password = Auth::user()->smtp_password;
        
        $view = match ($this->type) {
            'new' => 'emails.new-appointment-mga-mail', // done Tested
            'confirm_appointment' => 'emails.confirm-appointment-mga-mail', //done Tested
            'update' => 'emails.update-appointment-mga-mail', //done Tested
            'cancel' => 'emails.cancel-appointment-mga-mail', // done Tested
        };
        
        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject("Appointment Notification - {$this->appointment->file->mga_reference}")
                    ->with(['appointment' => $this->appointment]);
    }
}
