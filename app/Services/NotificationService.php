<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Notifications\AppointmentNotification;
use App\Notifications\FileNotification;
use App\Models\File;
use App\Models\Appointment;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send a general notification to a user
     */
    public static function sendGeneralNotification(User $user, $title, $message, $type = 'info', $actionUrl = null, $actionText = null)
    {
        $notification = new GeneralNotification($title, $message, $type, $actionUrl, $actionText);
        $user->notify($notification);
        $notification->sendFilamentNotification($user);
    }

    /**
     * Send a general notification to multiple users
     */
    public static function sendGeneralNotificationToUsers($users, $title, $message, $type = 'info', $actionUrl = null, $actionText = null)
    {
        $notification = new GeneralNotification($title, $message, $type, $actionUrl, $actionText);
        Notification::send($users, $notification);
        
        foreach ($users as $user) {
            $notification->sendFilamentNotification($user);
        }
    }

    /**
     * Send an appointment notification
     */
    public static function sendAppointmentNotification(User $user, Appointment $appointment, $type = 'created')
    {
        $notification = new AppointmentNotification($appointment, $type);
        $user->notify($notification);
        $notification->sendFilamentNotification($user);
    }

    /**
     * Send a file notification
     */
    public static function sendFileNotification(User $user, File $file, $action = 'created', $message = null)
    {
        $notification = new FileNotification($file, $action, $message);
        $user->notify($notification);
        $notification->sendFilamentNotification($user);
    }

    /**
     * Send notification to all admin users
     */
    public static function notifyAdmins($title, $message, $type = 'info', $actionUrl = null, $actionText = null)
    {
        $adminUsers = User::role(['Admin', 'Super Admin'])->get();
        self::sendGeneralNotificationToUsers($adminUsers, $title, $message, $type, $actionUrl, $actionText);
    }

    /**
     * Send notification to all users with a specific role
     */
    public static function notifyUsersWithRole($role, $title, $message, $type = 'info', $actionUrl = null, $actionText = null)
    {
        $users = User::role($role)->get();
        self::sendGeneralNotificationToUsers($users, $title, $message, $type, $actionUrl, $actionText);
    }

    /**
     * Send success notification
     */
    public static function success(User $user, $title, $message, $actionUrl = null, $actionText = null)
    {
        self::sendGeneralNotification($user, $title, $message, 'success', $actionUrl, $actionText);
    }

    /**
     * Send warning notification
     */
    public static function warning(User $user, $title, $message, $actionUrl = null, $actionText = null)
    {
        self::sendGeneralNotification($user, $title, $message, 'warning', $actionUrl, $actionText);
    }

    /**
     * Send danger/error notification
     */
    public static function danger(User $user, $title, $message, $actionUrl = null, $actionText = null)
    {
        self::sendGeneralNotification($user, $title, $message, 'danger', $actionUrl, $actionText);
    }

    /**
     * Send info notification
     */
    public static function info(User $user, $title, $message, $actionUrl = null, $actionText = null)
    {
        self::sendGeneralNotification($user, $title, $message, 'info', $actionUrl, $actionText);
    }
} 