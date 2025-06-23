<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class AppointmentNotification extends Notification
{
    use Queueable;

    protected $appointment;
    protected $type;

    /**
     * Create a new notification instance.
     */
    public function __construct($appointment, $type = 'created')
    {
        $this->appointment = $appointment;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Appointment ' . ucfirst($this->type))
            ->line('Your appointment has been ' . $this->type . '.')
            ->line('Appointment Date: ' . $this->appointment->appointment_date)
            ->action('View Appointment', url('/admin/appointments/' . $this->appointment->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'type' => $this->type,
            'message' => 'Appointment ' . $this->type . ' for ' . $this->appointment->patient->name,
            'appointment_date' => $this->appointment->appointment_date,
        ];
    }

    /**
     * Send a Filament notification to the database
     */
    public function sendFilamentNotification($user)
    {
        FilamentNotification::make()
            ->title('Appointment ' . ucfirst($this->type))
            ->body('Appointment for ' . $this->appointment->patient->name . ' has been ' . $this->type)
            ->success()
            ->sendToDatabase($user);
    }
} 