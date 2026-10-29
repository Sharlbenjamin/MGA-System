# MGA System - Notification System

This document explains how to use the notification system in the MGA System.

## Overview

The notification system is built on Laravel's notification framework and integrated with Filament for a seamless user experience. It supports both database notifications and Filament notifications.

## Features

- ✅ Database notifications table (already migrated)
- ✅ Filament database notifications integration
- ✅ Multiple notification types (success, warning, danger, info)
- ✅ Notification management in admin panel
- ✅ Bulk actions for notifications
- ✅ Notification service for easy usage
- ✅ Test command for demonstration

## Components

### 1. Database Table
- **Table**: `notifications`
- **Migration**: `2025_03_06_160609_create_notifications_table.php`
- **Status**: ✅ Already migrated and ready

### 2. Notification Classes

#### GeneralNotification
A flexible notification class for general purposes.

```php
use App\Notifications\GeneralNotification;

$notification = new GeneralNotification(
    'Title', 
    'Message', 
    'success', // or 'warning', 'danger', 'info'
    '/admin/files/1', // optional action URL
    'View File' // optional action text
);
```

#### AppointmentNotification
Specific notification for appointment events.

```php
use App\Notifications\AppointmentNotification;

$notification = new AppointmentNotification($appointment, 'created');
```

#### FileNotification
Specific notification for file events.

```php
use App\Notifications\FileNotification;

$notification = new FileNotification($file, 'created', 'Custom message');
```

### 3. NotificationService
A service class that provides easy methods to send notifications.

```php
use App\Services\NotificationService;

// Send to a single user
NotificationService::success($user, 'Title', 'Message');
NotificationService::warning($user, 'Title', 'Message');
NotificationService::danger($user, 'Title', 'Message');
NotificationService::info($user, 'Title', 'Message');

// Send to multiple users
NotificationService::notifyAdmins('Title', 'Message', 'success');
NotificationService::notifyUsersWithRole('Admin', 'Title', 'Message', 'warning');
```

### 4. Filament Integration
- **Resource**: `NotificationResource` - Manage notifications in admin panel
- **Panel Configuration**: Database notifications enabled in `AdminPanelProvider`
- **Navigation**: Available under "System" group in admin panel

## Usage Examples

### 1. Sending Notifications in Controllers

```php
use App\Services\NotificationService;

public function store(Request $request)
{
    // Your logic here...
    
    // Send notification to the current user
    NotificationService::success(
        auth()->user(),
        'File Created',
        'File has been created successfully.',
        '/admin/files/' . $file->id,
        'View File'
    );
    
    // Or notify all admins
    NotificationService::notifyAdmins(
        'New File Created',
        'A new file has been created by ' . auth()->user()->name,
        'info',
        '/admin/files/' . $file->id,
        'View File'
    );
}
```

### 2. Sending Notifications in Models (Observers/Events)

```php
use App\Services\NotificationService;

// In a model observer
public function created(File $file)
{
    // Notify the file creator
    NotificationService::success(
        $file->user,
        'File Created',
        "File {$file->file_number} has been created successfully."
    );
    
    // Notify admins
    NotificationService::notifyAdmins(
        'New File Created',
        "File {$file->file_number} has been created by {$file->user->name}",
        'info'
    );
}
```

### 3. Using Direct Notification Classes

```php
use App\Notifications\GeneralNotification;
use App\Notifications\FileNotification;

// General notification
$notification = new GeneralNotification(
    'System Update',
    'The system will be updated tonight at 2 AM.',
    'warning'
);
$user->notify($notification);
$notification->sendFilamentNotification($user);

// File notification
$notification = new FileNotification($file, 'updated', 'File status changed');
$user->notify($notification);
$notification->sendFilamentNotification($user);
```

## Testing the System

### 1. Using the Test Command

```bash
# Test with the first user
php artisan notifications:test

# Test with a specific user
php artisan notifications:test 1
```

### 2. Manual Testing

```php
// In tinker or a controller
use App\Services\NotificationService;
use App\Models\User;

$user = User::first();
NotificationService::success($user, 'Test', 'This is a test notification');
```

## Admin Panel Features

### 1. Notification Management
- View all notifications
- Filter by read/unread status
- Mark notifications as read/unread
- Delete notifications
- Bulk actions

### 2. Navigation
- Located under "System" group
- Icon: Bell icon
- Label: "Notifications"

### 3. Table Features
- Type column (shows notification class name)
- Message column (shows notification content)
- User column (shows recipient)
- Status column (read/unread indicator)
- Created date column
- Search functionality
- Sorting capabilities

## Configuration

### 1. Filament Panel Configuration
Database notifications are already enabled in `AdminPanelProvider.php`:

```php
->databaseNotifications()
```

### 2. User Model
The `User` model already has the `Notifiable` trait:

```php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    // ...
}
```

## Best Practices

### 1. Notification Types
- **Success**: Use for successful operations
- **Warning**: Use for important notices that need attention
- **Danger**: Use for errors or critical issues
- **Info**: Use for general information

### 2. Message Content
- Keep messages concise but informative
- Include relevant IDs or references
- Provide action URLs when appropriate

### 3. Performance
- Use queues for notifications if sending to many users
- Consider using bulk notifications for system-wide announcements

### 4. Security
- Validate user permissions before sending notifications
- Sanitize notification content to prevent XSS

## Troubleshooting

### 1. Notifications Not Appearing
- Check if the user has the `Notifiable` trait
- Verify the notification is being sent to the correct user
- Check the database for notification records

### 2. Filament Notifications Not Showing
- Ensure `databaseNotifications()` is enabled in panel configuration
- Check if the notification has a `sendFilamentNotification` method
- Verify the user is logged into the correct panel

### 3. Migration Issues
- Run `php artisan migrate:status` to check migration status
- If needed, run `php artisan migrate` to run pending migrations

## Files Created/Modified

### New Files
- `app/Notifications/GeneralNotification.php`
- `app/Notifications/FileNotification.php`
- `app/Services/NotificationService.php`
- `app/Filament/Resources/NotificationResource.php`
- `app/Filament/Resources/NotificationResource/Pages/ListNotifications.php`
- `app/Filament/Resources/NotificationResource/Pages/EditNotification.php`
- `app/Console/Commands/TestNotifications.php`
- `NOTIFICATIONS_README.md`

### Existing Files (Already Configured)
- `database/migrations/2025_03_06_160609_create_notifications_table.php` ✅
- `app/Models/User.php` (has Notifiable trait) ✅
- `app/Providers/Filament/AdminPanelProvider.php` (has databaseNotifications) ✅
- `app/Notifications/AppointmentNotification.php` ✅

## Next Steps

1. **Test the system** using the provided test command
2. **Integrate notifications** into your existing controllers and models
3. **Customize notification content** based on your business requirements
4. **Add more notification types** as needed for your application
5. **Set up notification preferences** if users should be able to control what they receive

The notification system is now fully set up and ready to use! 