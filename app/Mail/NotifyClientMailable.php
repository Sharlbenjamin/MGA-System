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
            'file_created' => 'emails.client.file-created-client-mail',
            'file_void' => 'emails.client.file-void-mail',
            'file_cancelled' => 'emails.client.file-cancelled-mail',
            'file_hold' => 'emails.client.file-hold-client-mail',
            'file_available' => 'emails.client.available-appointments-mail',
            'file_assisted' => 'emails.client.client-assisted-mail',
            //'appointment_reminder' => 'emails.client.appointment-reminder-mail',
            //'appointment_created' => 'emails.client.new-appointment-client-mail',
            'appointment_confirmed' => 'emails.client.confirm-appointment-client-mail',
            //'appointment_cancelled' => 'emails.client.cancel-appointment-client-mail',
            //'appointment_updated' => 'emails.client.update-appointment-client-mail',
        };

        if ($this->data instanceof File) {
            $file = $this->data;
        } else {
            $file = $this->data->file;
        }
        $header = match ($this->type) {
            'file_created' => 'File Created - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'file_void' => 'File Cancelled - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'file_cancelled' => 'File Cancelled - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'file_hold' => 'Appointment Hold - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'file_available' => 'Available Appointments - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'file_assisted' => 'Client Assisted - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'appointment_reminder' => 'Appointment Reminder - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'appointment_created' => 'New Appointment - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'appointment_confirmed' => 'Appointment Confirmation - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'appointment_cancelled' => 'Appointment Cancellation - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
            'appointment_updated' => 'Appointment Update - ' . $file->mga_reference. ' - ' . $file->patient->client->company_name,
        };

        return $this->view($view)
                    ->from($username, Auth::user()->name)
                    ->subject($header . " - " . ($file->mga_reference ?? 'No Reference') . " - " . ($file->patient->client->company_name ?? 'No Reference'))
                    ->with(['file' => $file]);
    }

}
