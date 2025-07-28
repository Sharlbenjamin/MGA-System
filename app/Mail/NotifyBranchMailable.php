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
            'appointment_created' => 'emails.branch.new-appointment-branch-mail',
            'appointment_confirmed' => 'emails.branch.confirm-appointment-branch-mail',
            'appointment_updated' => 'emails.branch.update-appointment-branch-mail',
            'appointment_cancelled' => 'emails.branch.cancel-appointment-branch-mail',
        };

        $subject = match ($this->type) {
            'appointment_created' => 'Appointment Request - ' . $this->appointment->file->patient->name . ' - ' . $this->appointment->file->mga_reference,
            'appointment_confirmed' => 'Appointment Confirmed - ' . $this->appointment->file->patient->name . ' - ' . $this->appointment->file->mga_reference,
            'appointment_updated' => 'Appointment Updated - ' . $this->appointment->file->patient->name . ' - ' . $this->appointment->file->mga_reference,
            'appointment_cancelled' => 'Appointment Cancelled - ' . $this->appointment->file->patient->name . ' - ' . $this->appointment->file->mga_reference,
        };

        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($subject)
                    ->with(['appointment' => $this->appointment]);
    }

}
