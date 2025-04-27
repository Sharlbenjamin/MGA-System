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

class NotifyClientMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $type;
    public $data;

    /**
     * Create a new message instance.
     */
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

        // Override only MAIL_USERNAME and MAIL_PASSWORD dynamically
        $username = Auth::user()->smtp_username;

        $view = match ($this->type) {
            'New' => 'emails.client.file-created-client-mail',
            'Requesting GOP' => 'emails.client.requesting-gop-mail',
            //'file_void' => 'emails.client.file-void-mail',
            'Cancelled' => 'emails.client.file-cancelled-mail',
            'Hold' => 'emails.client.file-hold-client-mail',
            'Available' => 'emails.client.available-appointments-mail',
            'Assisted' => 'emails.client.client-assisted-mail',
            'ask_client' => 'emails.client.ask-client-mail',
            //'appointment_reminder' => 'emails.client.appointment-reminder-mail',
            //'appointment_created' => 'emails.client.new-appointment-client-mail',
            'Confirmed' => 'emails.client.confirm-appointment-client-mail',
            //'appointment_cancelled' => 'emails.client.cancel-appointment-client-mail',
            //'appointment_updated' => 'emails.client.update-appointment-client-mail',
        };

        if ($this->data instanceof File) {
            $file = $this->data;
        } else {
            $file = $this->data->file;
        }
        $header = $file->mga_reference . ' | '. $file->patient->name . ' | '. $file->client_reference;


        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($header)
                    ->with(['file' => $file]);
    }

}
