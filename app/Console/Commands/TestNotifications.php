<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Models\User;

class TestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the notification system by sending sample notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
        } else {
            $user = User::first();
            if (!$user) {
                $this->error("No users found in the database.");
                return 1;
            }
        }

        $this->info("Sending test notifications to user: {$user->name} ({$user->email})");

        // Test different types of notifications
        NotificationService::success(
            $user,
            'Test Success Notification',
            'This is a test success notification from the command line.',
            '/admin/notifications',
            'View Notifications'
        );

        NotificationService::warning(
            $user,
            'Test Warning Notification',
            'This is a test warning notification from the command line.',
            '/admin/notifications',
            'View Notifications'
        );

        NotificationService::info(
            $user,
            'Test Info Notification',
            'This is a test info notification from the command line.',
            '/admin/notifications',
            'View Notifications'
        );

        NotificationService::danger(
            $user,
            'Test Danger Notification',
            'This is a test danger notification from the command line.',
            '/admin/notifications',
            'View Notifications'
        );

        $this->info('Test notifications sent successfully!');
        $this->info('Check the admin panel to see the notifications.');

        return 0;
    }
} 