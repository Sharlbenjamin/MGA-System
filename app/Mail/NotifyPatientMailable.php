<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;

class NotifyPatientMailable extends Mailable
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
            'update' => 'emails.update-appointment-branch-mail',
            'cancel' => 'emails.cancel-appointment-branch-mail',
            'confirm' => 'emails.confirm-appointment-patient-mail',
            'available' => 'emails.available-appointments-mail',
            'reminder' => 'emails.reminder-appointment-mail',
            'file_created' => 'emails.file-created-client-mail',
            'file_cancelled' => 'emails.file-cancelled-mail',
            'assisted' => 'emails.patient-assisted-mail',
            default => 'emails.default-notification',
        };
        
        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject("Appointment Notification - {$this->type}")
                    ->with(['appointment' => $this->appointment]);
    }
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notify Patient Mailable',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'view.name',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
