<?php

namespace App\Observers;

use App\Models\Task;

class TaskObserver
{
    /**
     * When a task is marked done, mark the assignee's persistent "task assigned" notifications as read
     * so they disappear from the notification bell.
     */
    public function updated(Task $task): void
    {
        if (! $task->isDirty('is_done')) {
            return;
        }

        if (! $task->is_done) {
            return;
        }

        $this->markTaskNotificationsAsRead($task);
    }

    private function markTaskNotificationsAsRead(Task $task): void
    {
        $user = $task->user;
        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->get()
            ->filter(function ($notification) use ($task) {
                $data = $notification->data ?? [];
                $taskId = $data['viewData']['task_id'] ?? null;

                return $taskId === (int) $task->id;
            })
            ->each(fn ($notification) => $notification->markAsRead());
    }
}
