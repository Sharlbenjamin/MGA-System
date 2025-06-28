<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use App\Models\File;

class FileNotification extends Notification
{
    use Queueable;

    protected $file;
    protected $action;
    protected $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(File $file, $action = 'created', $message = null)
    {
        $this->file = $file;
        $this->action = $action;
        $this->message = $message ?? "File has been {$action}";
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
            ->subject('File ' . ucfirst($this->action))
            ->line($this->message)
            ->line('File: ' . $this->file->file_number)
            ->line('Client: ' . $this->file->client->name)
            ->action('View File', url('/admin/files/' . $this->file->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'file_id' => $this->file->id,
            'file_number' => $this->file->file_number,
            'client_name' => $this->file->client->name,
            'action' => $this->action,
            'message' => $this->message,
            'action_url' => '/admin/files/' . $this->file->id,
        ];
    }

    /**
     * Send a Filament notification to the database
     */
    public function sendFilamentNotification($user)
    {
        $notification = FilamentNotification::make()
            ->title('File ' . ucfirst($this->action))
            ->body($this->message . ' - ' . $this->file->file_number . ' (' . $this->file->client->name . ')');

        // Set notification type based on action
        switch ($this->action) {
            case 'created':
                $notification->success();
                break;
            case 'updated':
                $notification->info();
                break;
            case 'deleted':
                $notification->danger();
                break;
            default:
                $notification->info();
                break;
        }

        $notification->sendToDatabase($user);
    }
} 