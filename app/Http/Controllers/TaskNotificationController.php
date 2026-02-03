<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class TaskNotificationController extends Controller
{
    /**
     * Mark the task as done and mark the related notification as read.
     */
    public function done(Task $task): RedirectResponse
    {
        if (! request()->hasValidSignature()) {
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('error', 'Invalid or expired link.');
        }

        if ($task->user_id !== Auth::id()) {
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('error', 'You are not assigned to this task.');
        }

        $task->update([
            'is_done' => true,
            'done_by' => Auth::id(),
        ]);

        $this->markTaskNotificationAsRead($task);

        Notification::make()
            ->title('Task marked as done')
            ->body("Task: {$task->title}")
            ->success()
            ->send();

        return redirect()->back();
    }

    /**
     * Mark the task as not done and mark the related notification as read.
     */
    public function notDone(Task $task): RedirectResponse
    {
        if (! request()->hasValidSignature()) {
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('error', 'Invalid or expired link.');
        }

        if ($task->user_id !== Auth::id()) {
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('error', 'You are not assigned to this task.');
        }

        $task->update([
            'is_done' => false,
            'done_by' => null,
        ]);

        $this->markTaskNotificationAsRead($task);

        Notification::make()
            ->title('Task marked as not done')
            ->body("Task: {$task->title}")
            ->warning()
            ->send();

        return redirect()->back();
    }

    /**
     * Find unread notifications that reference this task (via viewData.task_id) and mark them as read.
     */
    private function markTaskNotificationAsRead(Task $task): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->get()
            ->filter(function ($notification) use ($task) {
                $data = $notification->data;
                $taskId = $data['viewData']['task_id'] ?? null;

                return $taskId === (int) $task->id;
            })
            ->each(fn ($notification) => $notification->markAsRead());
    }
}
