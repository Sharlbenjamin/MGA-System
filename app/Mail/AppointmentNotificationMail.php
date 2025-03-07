<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AppointmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $type;
    public $appointment;
    public $user;

    public function __construct($type, Appointment $appointment)
    {
        $this->type = $type;
        $this->appointment = $appointment;
        $this->user = Auth::user();

        // Set SMTP credentials dynamically
        Config::set('mail.mailers.smtp.username', $this->user->smtp_username ?? Config::get('mail.mailers.smtp.username'));
        Config::set('mail.mailers.smtp.password', $this->user->smtp_password ?? Config::get('mail.mailers.smtp.password'));

        Log::info("Using SMTP Username: " . Config::get('mail.mailers.smtp.username'));
    }

    public function build()
    {
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

        Log::info("Final Email View: {$view}");

        return $this->view($view)
                    ->from($this->user->email, $this->user->name)
                    ->subject("Appointment Notification - {$this->type}")
                    ->with(['appointment' => $this->appointment]);
    }
}