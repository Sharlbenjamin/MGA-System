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
    public $message;

    /**
     * Create a new message instance.
     */
    public function __construct($type, $data, $message = null)
    {
        $this->type = $type;
        $this->data = $data;
        $this->message = $message;
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
            'Void' => 'emails.client.file-void-mail',
            'Cancelled' => 'emails.client.file-cancelled-mail',
            'Hold' => 'emails.client.file-hold-client-mail',
            'Available' => 'emails.client.available-appointments-mail',
            'Assisted' => 'emails.client.client-assisted-mail',
            'Custom' => 'emails.client.custom-client-mail',
            'ask_client' => 'emails.client.ask-client-mail',
            'Confirmed' => 'emails.client.confirm-appointment-client-mail',
        };

        if ($this->data instanceof File) {
            $file = $this->data;
        } else {
            $file = $this->data->file;
        }
        $header = $file->mga_reference . ' | '. $file->patient->name . ' | '. $file->client_reference;

        $viewData = [
            'file' => $file
        ];

        // Only add message to view data if this is a custom notification
        if ($this->type === 'Custom' && $this->message) {
            $viewData['the_message'] = $this->message;
        }

        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($header)
                    ->with($viewData);
    }

}
