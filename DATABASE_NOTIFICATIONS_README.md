# Database Notifications Setup

## Overview
Database notifications have been successfully installed and configured in your MGA system. This allows you to send persistent notifications to users that are stored in the database and displayed in the Filament admin panel.

## What's Already Configured

✅ **Database Migration**: The `notifications` table has been created and migrated  
✅ **User Model**: The `User` model has the `Notifiable` trait  
✅ **Filament Panel**: Database notifications are enabled in `AdminPanelProvider.php`  
✅ **Layout**: The notifications component is added to `app.blade.php`  
✅ **Package**: `filament/notifications` package is installed  

## How to Use Database Notifications

### 1. Send a Simple Filament Notification

```php
use Filament\Notifications\Notification;

// Send to current user
Notification::make()
    ->title('Success!')
    ->body('Your action was completed successfully.')
    ->success()
    ->sendToDatabase(auth()->user());

// Send to specific user
Notification::make()
    ->title('New Appointment')
    ->body('You have a new appointment scheduled.')
    ->warning()
    ->sendToDatabase($user);
```

### 2. Use Laravel Notification Classes

```php
use App\Notifications\AppointmentNotification;

// Send using Laravel's notification system
$user->notify(new AppointmentNotification($appointment, 'created'));

// Or use the Filament method directly
$notification = new AppointmentNotification($appointment, 'created');
$notification->sendFilamentNotification($user);
```

### 3. Send Notifications from Filament Resources

In your Filament resource actions or pages:

```php
use Filament\Notifications\Notification;

// In a resource action
public function createAppointment(): void
{
    // Your logic here...
    
    Notification::make()
        ->title('Appointment Created')
        ->body('The appointment has been successfully created.')
        ->success()
        ->sendToDatabase(auth()->user());
}
```

## Notification Types

- `->success()` - Green notification
- `->warning()` - Yellow notification  
- `->danger()` - Red notification
- `->info()` - Blue notification

## Testing

You can test the notifications by visiting:
```
/test-notification
```

This will send a test notification to the currently authenticated user.

## Viewing Notifications

Users can view their notifications by:
1. Looking for the notification bell icon in the Filament admin panel
2. Clicking on it to see all notifications
3. Marking notifications as read or clearing them

## Customization

### Custom Notification Classes
See `app/Notifications/AppointmentNotification.php` for an example of how to create custom notification classes.

### Styling
Notifications use Filament's built-in styling. You can customize the appearance by modifying the Filament theme.

## Troubleshooting

If notifications aren't appearing:
1. Make sure you're logged in
2. Check that the `@livewire('notifications')` component is in your layout
3. Verify the user has the `Notifiable` trait
4. Check the browser console for any JavaScript errors

## Next Steps

Consider implementing notifications for:
- New patient registrations
- Appointment reminders
- File status changes
- System alerts
- User activity notifications 