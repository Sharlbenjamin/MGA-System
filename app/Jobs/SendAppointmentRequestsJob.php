<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\Gop;
use App\Models\User;
use App\Services\AppointmentRequestSender;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public int $fileId,
        public array $formData,
        public int $userId,
        public ?int $gopId = null,
    ) {}

    public function handle(AppointmentRequestSender $sender): void
    {
        $file = File::query()->find($this->fileId);
        $user = User::query()->find($this->userId);

        if (! $file || ! $user) {
            Log::error('SendAppointmentRequestsJob missing file or user', [
                'file_id' => $this->fileId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        $gop = $this->gopId ? Gop::query()->find($this->gopId) : null;
        $result = $sender->send($file, $this->formData, $user, $gop);

        if ($result['skipped']) {
            $this->notifyUser($user, 'No Recipients Selected', $result['message'] ?? 'No recipients selected.', 'warning');

            return;
        }

        if ($result['message']) {
            $this->notifyUser($user, 'Failed to Send', $result['message'], 'danger');

            return;
        }

        if ($result['success'] > 0) {
            $this->notifyUser(
                $user,
                'Appointment Requests Sent',
                "Successfully sent to {$result['success']} provider(s).",
                'success',
            );
        }

        if ($result['failure'] > 0) {
            $this->notifyUser(
                $user,
                'Some Requests Failed',
                "Failed to send to {$result['failure']} provider(s).",
                'warning',
            );
        }
    }

    protected function notifyUser(User $user, string $title, string $body, string $status): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->{$status}()
            ->sendToDatabase($user);
    }
}
