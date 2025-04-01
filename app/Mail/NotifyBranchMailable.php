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

        $header = match ($this->type) {
            // lets add the mga_reference and the branch_name to the subject
            'appointment_created' => 'Branch New Appointment - ' . $this->appointment->file->mga_reference . ' - ' . $this->appointment->providerBranch->branch_name,
            'appointment_confirmed' => 'Branch Appointment Confirmation - ' . $this->appointment->file->mga_reference . ' - ' . $this->appointment->providerBranch->branch_name,
            'appointment_updated' => 'Branch Appointment Update - ' . $this->appointment->file->mga_reference . ' - ' . $this->appointment->providerBranch->branch_name,
            'appointment_cancelled' => 'Branch Appointment Cancellation - ' . $this->appointment->file->mga_reference . ' - ' . $this->appointment->providerBranch->branch_name,
        };

        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($header)
                    ->with(['appointment' => $this->appointment]);
    }

}
