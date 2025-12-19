<?php

namespace App\Services;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create and send a notification.
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?Event $event = null,
        bool $sendEmail = true,
        bool $sendPush = true
    ): Notification {
        // Check user preferences
        $preferences = $this->getUserPreferences($user);

        if (!$this->shouldSendByType($preferences, $type)) {
            // Still create notification but don't send via channels
            $sendEmail = false;
            $sendPush = false;
        }

        $channels = [];
        if ($sendEmail && ($preferences['email_notifications'] ?? true)) {
            $channels[] = 'email';
        }
        if ($sendPush && ($preferences['push_notifications'] ?? false)) {
            $channels[] = 'push';
        }

        // Create notification record
        $notification = Notification::create([
            'user_id' => $user->id,
            'event_id' => $event?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'sent_via' => !empty($channels) ? implode(',', $channels) : 'app',
        ]);

        // Dispatch jobs for each channel
        if (in_array('email', $channels)) {
            SendEmailNotificationJob::dispatch($notification);
        }

        if (in_array('push', $channels)) {
            SendPushNotificationJob::dispatch($notification);
        }

        return $notification;
    }

    /**
     * Create a task reminder notification.
     */
    public function sendTaskReminder(Task $task, int $daysUntilDue): Notification
    {
        $dueText = $daysUntilDue === 0 ? "aujourd'hui" : "dans {$daysUntilDue} jour(s)";

        return $this->send(
            $task->assignee,
            'task_reminder',
            'Rappel de tâche',
            "La tâche \"{$task->title}\" pour l'événement \"{$task->event->title}\" est due {$dueText}.",
            $task->event
        );
    }

    /**
     * Create a guest reminder notification.
     */
    public function sendGuestReminder(Guest $guest): Notification
    {
        return $this->send(
            $guest->event->user,
            'guest_reminder',
            'Rappel RSVP en attente',
            "{$guest->name} n'a pas encore répondu à l'invitation pour \"{$guest->event->title}\".",
            $guest->event
        );
    }

    /**
     * Create a budget alert notification.
     */
    public function sendBudgetAlert(Event $event, string $alertType, float $percentage): Notification
    {
        $message = match ($alertType) {
            'threshold' => "Vous avez utilisé {$percentage}% de votre budget pour \"{$event->title}\".",
            'over_budget' => "Attention ! Vous avez dépassé votre budget pour \"{$event->title}\".",
            default => "Alerte budget pour \"{$event->title}\".",
        };

        return $this->send(
            $event->user,
            'budget_alert',
            'Alerte budget',
            $message,
            $event
        );
    }

    /**
     * Create an event reminder notification.
     */
    public function sendEventReminder(Event $event, int $daysUntilEvent): Notification
    {
        $dueText = $daysUntilEvent === 0 ? "aujourd'hui" : "dans {$daysUntilEvent} jour(s)";

        return $this->send(
            $event->user,
            'event_reminder',
            'Rappel événement',
            "Votre événement \"{$event->title}\" a lieu {$dueText}.",
            $event
        );
    }

    /**
     * Create a collaboration invite notification.
     */
    public function sendCollaborationInvite(User $invitee, Event $event, User $inviter): Notification
    {
        return $this->send(
            $invitee,
            'collaboration_invite',
            'Invitation à collaborer',
            "{$inviter->name} vous invite à collaborer sur \"{$event->title}\".",
            $event
        );
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): Notification
    {
        $notification->markAsRead();

        return $notification;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get notifications for a user.
     */
    public function getUserNotifications(User $user, bool $unreadOnly = false): Collection
    {
        $query = Notification::where('user_id', $user->id)
            ->with('event')
            ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->get();
    }

    /**
     * Get unread count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get user notification preferences.
     */
    public function getUserPreferences(User $user): array
    {
        // Default preferences - can be extended to use a preferences table/column
        $defaults = [
            'task_reminder' => true,
            'guest_reminder' => true,
            'budget_alert' => true,
            'event_reminder' => true,
            'collaboration_invite' => true,
            'email_notifications' => true,
            'push_notifications' => false,
        ];

        // If user has notification_preferences JSON column
        if (isset($user->notification_preferences)) {
            return array_merge($defaults, $user->notification_preferences);
        }

        return $defaults;
    }

    /**
     * Update user notification preferences.
     */
    public function updatePreferences(User $user, array $preferences): void
    {
        // Validate and sanitize preferences
        $validKeys = [
            'task_reminder',
            'guest_reminder',
            'budget_alert',
            'event_reminder',
            'collaboration_invite',
            'email_notifications',
            'push_notifications',
        ];

        // Get current preferences
        $currentPreferences = $user->notification_preferences ?? [];

        // Merge new preferences
        foreach ($validKeys as $key) {
            if (isset($preferences[$key])) {
                $currentPreferences[$key] = (bool) $preferences[$key];
            }
        }

        // Store in user model (requires notification_preferences column)
        $user->update(['notification_preferences' => $currentPreferences]);
    }

    /**
     * Check if notification should be sent based on type.
     */
    protected function shouldSendByType(array $preferences, string $type): bool
    {
        return $preferences[$type] ?? true;
    }

    /**
     * Delete old notifications.
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($daysOld))
            ->whereNotNull('read_at')
            ->delete();
    }

    /**
     * Get notifications grouped by date.
     */
    public function getGroupedByDate(User $user, int $limit = 50): Collection
    {
        $notifications = Notification::where('user_id', $user->id)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $notifications->groupBy(function ($notification) {
            return $notification->created_at->format('Y-m-d');
        });
    }

    /**
     * Get notification statistics for a user.
     */
    public function getStatistics(User $user): array
    {
        $notifications = Notification::where('user_id', $user->id)->get();

        return [
            'total' => $notifications->count(),
            'unread' => $notifications->whereNull('read_at')->count(),
            'read' => $notifications->whereNotNull('read_at')->count(),
            'by_type' => $notifications->groupBy('type')->map->count()->toArray(),
        ];
    }

    /**
     * Delete a notification.
     */
    public function delete(Notification $notification): bool
    {
        return $notification->delete();
    }

    /**
     * Bulk delete notifications.
     */
    public function bulkDelete(User $user, array $notificationIds): int
    {
        return Notification::where('user_id', $user->id)
            ->whereIn('id', $notificationIds)
            ->delete();
    }

    /**
     * Register a device token for push notifications.
     */
    public function registerDeviceToken(User $user, string $token, string $platform = 'fcm'): void
    {
        // Store device token (requires device_tokens table or JSON column)
        // This is a placeholder - implement based on your push notification service
    }

    /**
     * Remove a device token.
     */
    public function removeDeviceToken(User $user, string $token): void
    {
        // Remove device token
        // This is a placeholder - implement based on your push notification service
    }
}
