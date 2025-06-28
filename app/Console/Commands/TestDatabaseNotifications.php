<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\NotificationService;
use Filament\Notifications\Notification;

class TestDatabaseNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notifications {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test database notifications system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if ($userId) {
            $user = User::find($userId);
        } else {
            $user = User::first();
        }

        if (!$user) {
            $this->error('No user found!');
            return 1;
        }

        $this->info("Testing notifications for user: {$user->name} ({$user->email})");

        // Test 1: Direct Filament notification
        $this->info('1. Testing direct Filament notification...');
        try {
            Notification::make()
                ->title('Test Notification')
                ->body('This is a test notification from the command line.')
                ->success()
                ->sendToDatabase($user);
            $this->info('✅ Direct Filament notification sent successfully');
        } catch (\Exception $e) {
            $this->error('❌ Direct Filament notification failed: ' . $e->getMessage());
        }

        // Test 2: NotificationService
        $this->info('2. Testing NotificationService...');
        try {
            NotificationService::success($user, 'Test Success', 'This is a test success notification');
            $this->info('✅ NotificationService success notification sent');
        } catch (\Exception $e) {
            $this->error('❌ NotificationService failed: ' . $e->getMessage());
        }

        // Test 3: General notification
        $this->info('3. Testing general notification...');
        try {
            NotificationService::sendGeneralNotification($user, 'Test General', 'This is a test general notification', 'info');
            $this->info('✅ General notification sent');
        } catch (\Exception $e) {
            $this->error('❌ General notification failed: ' . $e->getMessage());
        }

        // Check notification counts
        $this->info('4. Checking notification counts...');
        $totalNotifications = $user->notifications()->count();
        $unreadNotifications = $user->unreadNotifications()->count();
        
        $this->info("Total notifications: {$totalNotifications}");
        $this->info("Unread notifications: {$unreadNotifications}");

        // Show recent notifications
        $this->info('5. Recent notifications:');
        $recentNotifications = $user->notifications()->latest()->take(5)->get();
        
        foreach ($recentNotifications as $notification) {
            $data = $notification->data;
            $title = $data['title'] ?? $data['message'] ?? 'No title';
            $message = $data['message'] ?? $data['title'] ?? 'No message';
            $readStatus = $notification->read_at ? 'Read' : 'Unread';
            
            $this->line("  - {$title}: {$message} ({$readStatus})");
        }

        $this->info('✅ Database notification test completed successfully!');
        $this->info('');
        $this->info('To view notifications in Filament:');
        $this->info('1. Go to your admin panel');
        $this->info('2. Look for the notification bell icon in the top navigation');
        $this->info('3. Or go to /admin/notifications to see all notifications');
        
        return 0;
    }
} 