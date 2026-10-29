# Notification Service Guide

This guide explains how to use the centralized notification service for all database notifications in the MGA System.

## Overview

All database notifications are now organized in `app/Services/NotificationService.php`. This provides a single point of control for all notification functionality, making it easy to:
- Call notifications from anywhere in the application
- Modify notification behavior in one place
- Maintain consistency across the system

## Available Notification Methods

### General Notifications

```php
use App\Services\NotificationService;

// Send to a single user
NotificationService::sendGeneralNotification($user, $title, $message, $type, $actionUrl, $actionText);

// Send to multiple users
NotificationService::sendGeneralNotificationToUsers($users, $title, $message, $type, $actionUrl, $actionText);

// Send to all admins
NotificationService::notifyAdmins($title, $message, $type, $actionUrl, $actionText);

// Send to users with specific role
NotificationService::notifyUsersWithRole($role, $title, $message, $type, $actionUrl, $actionText);
```

### Appointment Notifications

```php
// Send appointment notification
NotificationService::sendAppointmentNotification($user, $appointment, $type);

// Send appointment request notifications (NEW - includes custom emails)
NotificationService::sendAppointmentRequestNotifications($file, $selectedBranches, $customEmails);

// Send appointment status change notification
NotificationService::sendAppointmentStatusNotification($appointment, $oldStatus, $newStatus);
```

### File Notifications

```php
// Send file notification
NotificationService::sendFileNotification($user, $file, $action, $message);

// Send file status change notification
NotificationService::sendFileStatusNotification($file, $oldStatus, $newStatus);

// Send file creation notification to all relevant users
NotificationService::sendFileCreationNotification($file);
```

### Task Notifications

```php
// Send task assignment notification
NotificationService::sendTaskAssignmentNotification($task, $assignedUser);

// Send task completion notification
NotificationService::sendTaskCompletionNotification($task);
```

### Convenience Methods

```php
// Quick notification types
NotificationService::success($user, $title, $message, $actionUrl, $actionText);
NotificationService::warning($user, $title, $message, $actionUrl, $actionText);
NotificationService::danger($user, $title, $message, $actionUrl, $actionText);
NotificationService::info($user, $title, $message, $actionUrl, $actionText);
```

### Bulk Operation Notifications

```php
// Send bulk operation result notification
NotificationService::sendBulkOperationNotification($operation, $successCount, $failureCount, $details);

// Send system health notification
NotificationService::sendSystemHealthNotification($status, $message, $details);
```

## New Features

### 1. Custom Email Support for Appointment Requests

The appointment request modal now supports sending notifications to custom email addresses in addition to provider branches.

**Usage:**
```php
// In the appointment request modal, users can now:
// 1. Select provider branches (existing functionality)
// 2. Add custom email addresses (new functionality)

$customEmails = ['custom@example.com', 'another@example.com'];
$selectedBranches = [1, 2, 3]; // Branch IDs

NotificationService::sendAppointmentRequestNotifications($file, $selectedBranches, $customEmails);
```

**UI Changes:**
- Added "Custom Email Addresses" section in the appointment request modal
- Users can add multiple custom email addresses
- Each email is validated before sending
- Custom emails receive the same detailed appointment information as provider branches

### 2. User Notification for Appointment Requests

When a user sends appointment requests, they now receive a database notification with the results.

**Features:**
- Shows which requests were successful
- Shows which requests failed and why
- Provides a summary of the operation
- Includes action buttons to view the file

**Notification Content:**
- Total number of notifications sent
- List of successful recipients
- List of failed recipients with reasons
- Link to view the file

## Implementation Examples

### Example 1: Sending Appointment Requests

```php
// In your controller or action
public function sendAppointmentRequests(File $file, array $data)
{
    $selectedBranches = collect($data['selected_branches'])
        ->filter(fn ($branch) => $branch['selected'])
        ->pluck('id')
        ->toArray();

    $customEmails = collect($data['custom_emails'])
        ->pluck('email')
        ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
        ->toArray();

    $result = NotificationService::sendAppointmentRequestNotifications(
        $file, 
        $selectedBranches, 
        $customEmails
    );

    return $result;
}
```

### Example 2: File Status Change

```php
// In your model observer or controller
public function updateFileStatus(File $file, $newStatus)
{
    $oldStatus = $file->status;
    $file->update(['status' => $newStatus]);

    NotificationService::sendFileStatusNotification($file, $oldStatus, $newStatus);
}
```

### Example 3: Task Assignment

```php
// When assigning a task
public function assignTask($task, User $assignedUser)
{
    $task->update(['assigned_user_id' => $assignedUser->id]);
    
    NotificationService::sendTaskAssignmentNotification($task, $assignedUser);
}
```

## Email Templates

### Appointment Request Email

The appointment request email system has been updated to use the standardized NotifiableEntity trait system.

**Current System Features:**
- Uses NotifyBranchMailable for all branch notifications
- Standardized email subjects with patient names
- Consistent template structure across all appointment types
- Symptoms included in all templates
- Location information removed as requested

## Database Notifications

All notifications are stored in the `notifications` table and can be viewed in the Filament admin panel.

**Notification Types:**
- `general_notification`
- `appointment_notification`
- `file_notification`

## Error Handling

The notification service includes comprehensive error handling:

```php
// Email failures are caught and reported
try {
    $providerBranch->notifyBranch('appointment_created', $file);
    $successfulBranches[] = $providerBranch->branch_name;
} catch (\Exception $e) {
    $skippedBranches[] = $providerBranch->branch_name . ' (Email failed)';
}
```

## Best Practices

1. **Always use the NotificationService** instead of calling notification classes directly
2. **Handle errors gracefully** - the service will report failures but won't crash
3. **Validate email addresses** before sending to custom emails
4. **Use appropriate notification types** (success, warning, danger, info)
5. **Include action URLs** when relevant for better user experience

## Migration from Old System

If you have existing code that directly uses notification classes, replace them with NotificationService calls:

**Before:**
```php
Mail::to($email)->send(new AppointmentRequestMail($file, $providerBranch));
```

**After:**
```php
$providerBranch->notifyBranch('appointment_created', $file);
```

## Troubleshooting

### Common Issues

1. **Emails not sending**: Check SMTP configuration and email validation
2. **Notifications not appearing**: Ensure the user has the correct permissions
3. **Custom emails failing**: Verify email format validation

### Debug Information

The notification service returns detailed information about the operation:

```php
$result = NotificationService::sendAppointmentRequestNotifications($file, $branches, $emails);

// $result contains:
[
    'successful' => ['Branch A', 'Branch B', 'Custom: email@example.com'],
    'skipped' => ['Branch C (Email failed)'],
    'total_sent' => 3,
    'total_skipped' => 1
]
```

This centralized approach makes the notification system more maintainable, consistent, and easier to extend with new features. 