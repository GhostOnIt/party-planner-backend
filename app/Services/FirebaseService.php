<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\WebPushConfig;

class FirebaseService
{
    protected ?Messaging $messaging = null;

    public function __construct()
    {
        if (config('firebase.projects.app.credentials.file')) {
            $this->messaging = app('firebase.messaging');
        }
    }

    /**
     * Check if Firebase is configured.
     */
    public function isConfigured(): bool
    {
        return $this->messaging !== null;
    }

    /**
     * Send notification to a single device.
     */
    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        if (!$this->isConfigured()) {
            Log::warning('Firebase not configured, skipping notification');
            return ['success' => false, 'message' => 'Firebase not configured'];
        }

        try {
            $notification = Notification::create($title, $body);

            if ($imageUrl) {
                $notification = $notification->withImageUrl($imageUrl);
            }

            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification)
                ->withData($data)
                ->withAndroidConfig($this->getAndroidConfig())
                ->withApnsConfig($this->getApnsConfig())
                ->withWebPushConfig($this->getWebPushConfig($title, $body, $imageUrl));

            $this->messaging->send($message);

            Log::info('Firebase notification sent', [
                'token' => substr($deviceToken, 0, 20) . '...',
                'title' => $title,
            ]);

            return ['success' => true, 'message' => 'Notification sent'];

        } catch (MessagingException $e) {
            Log::error('Firebase messaging error', [
                'error' => $e->getMessage(),
                'token' => substr($deviceToken, 0, 20) . '...',
            ]);

            if ($this->isInvalidToken($e)) {
                return ['success' => false, 'message' => 'Invalid token', 'invalid_token' => true];
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send notification to multiple devices.
     */
    public function sendToDevices(
        array $deviceTokens,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        if (!$this->isConfigured()) {
            Log::warning('Firebase not configured, skipping notifications');
            return ['success' => false, 'message' => 'Firebase not configured', 'results' => []];
        }

        if (empty($deviceTokens)) {
            return ['success' => true, 'message' => 'No tokens to send to', 'results' => []];
        }

        try {
            $notification = Notification::create($title, $body);

            if ($imageUrl) {
                $notification = $notification->withImageUrl($imageUrl);
            }

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data)
                ->withAndroidConfig($this->getAndroidConfig())
                ->withApnsConfig($this->getApnsConfig())
                ->withWebPushConfig($this->getWebPushConfig($title, $body, $imageUrl));

            $report = $this->messaging->sendMulticast($message, $deviceTokens);

            $results = [
                'success_count' => $report->successes()->count(),
                'failure_count' => $report->failures()->count(),
                'invalid_tokens' => [],
            ];

            // Collect invalid tokens
            foreach ($report->failures()->getItems() as $failure) {
                if ($failure->error() && $this->isInvalidTokenError($failure->error()->getMessage())) {
                    $results['invalid_tokens'][] = $failure->target()->value();
                }
            }

            Log::info('Firebase multicast sent', [
                'total' => count($deviceTokens),
                'success' => $results['success_count'],
                'failures' => $results['failure_count'],
            ]);

            return [
                'success' => true,
                'message' => "Sent to {$results['success_count']} devices",
                'results' => $results,
            ];

        } catch (MessagingException $e) {
            Log::error('Firebase multicast error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'results' => []];
        }
    }

    /**
     * Send notification to a user (all their devices).
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        $deviceTokens = $user->deviceTokens()->pluck('token')->toArray();

        if (empty($deviceTokens)) {
            return ['success' => true, 'message' => 'User has no registered devices'];
        }

        $result = $this->sendToDevices($deviceTokens, $title, $body, $data, $imageUrl);

        // Clean up invalid tokens
        if (!empty($result['results']['invalid_tokens'])) {
            $user->deviceTokens()
                ->whereIn('token', $result['results']['invalid_tokens'])
                ->delete();

            Log::info('Removed invalid device tokens', [
                'user_id' => $user->id,
                'count' => count($result['results']['invalid_tokens']),
            ]);
        }

        return $result;
    }

    /**
     * Send notification to multiple users.
     */
    public function sendToUsers(
        Collection $users,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        $deviceTokens = [];
        $userTokenMap = [];

        foreach ($users as $user) {
            $tokens = $user->deviceTokens()->pluck('token')->toArray();
            foreach ($tokens as $token) {
                $deviceTokens[] = $token;
                $userTokenMap[$token] = $user->id;
            }
        }

        if (empty($deviceTokens)) {
            return ['success' => true, 'message' => 'No devices to send to'];
        }

        return $this->sendToDevices($deviceTokens, $title, $body, $data, $imageUrl);
    }

    /**
     * Send notification to a topic.
     */
    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Firebase not configured'];
        }

        try {
            $notification = Notification::create($title, $body);

            if ($imageUrl) {
                $notification = $notification->withImageUrl($imageUrl);
            }

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data)
                ->withAndroidConfig($this->getAndroidConfig())
                ->withApnsConfig($this->getApnsConfig())
                ->withWebPushConfig($this->getWebPushConfig($title, $body, $imageUrl));

            $this->messaging->send($message);

            Log::info('Firebase topic notification sent', ['topic' => $topic, 'title' => $title]);

            return ['success' => true, 'message' => 'Topic notification sent'];

        } catch (MessagingException $e) {
            Log::error('Firebase topic error', ['error' => $e->getMessage(), 'topic' => $topic]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Subscribe tokens to a topic.
     */
    public function subscribeToTopic(array $tokens, string $topic): array
    {
        if (!$this->isConfigured() || empty($tokens)) {
            return ['success' => false, 'message' => 'Firebase not configured or no tokens'];
        }

        try {
            $this->messaging->subscribeToTopic($topic, $tokens);
            Log::info('Subscribed to topic', ['topic' => $topic, 'count' => count($tokens)]);
            return ['success' => true, 'message' => 'Subscribed to topic'];
        } catch (MessagingException $e) {
            Log::error('Topic subscription error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Unsubscribe tokens from a topic.
     */
    public function unsubscribeFromTopic(array $tokens, string $topic): array
    {
        if (!$this->isConfigured() || empty($tokens)) {
            return ['success' => false, 'message' => 'Firebase not configured or no tokens'];
        }

        try {
            $this->messaging->unsubscribeFromTopic($topic, $tokens);
            Log::info('Unsubscribed from topic', ['topic' => $topic, 'count' => count($tokens)]);
            return ['success' => true, 'message' => 'Unsubscribed from topic'];
        } catch (MessagingException $e) {
            Log::error('Topic unsubscription error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Validate a device token.
     */
    public function validateToken(string $token): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            // Send a dry run message to validate the token
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create('Test', 'Test'));

            $this->messaging->send($message, true); // true = dry run
            return true;
        } catch (MessagingException $e) {
            return false;
        }
    }

    /**
     * Get Android specific configuration.
     */
    protected function getAndroidConfig(): AndroidConfig
    {
        return AndroidConfig::fromArray([
            'priority' => 'high',
            'notification' => [
                'sound' => 'default',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'channel_id' => 'party_planner_notifications',
            ],
        ]);
    }

    /**
     * Get iOS (APNs) specific configuration.
     */
    protected function getApnsConfig(): ApnsConfig
    {
        return ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'sound' => 'default',
                    'badge' => 1,
                ],
            ],
        ]);
    }

    /**
     * Get Web Push specific configuration.
     */
    protected function getWebPushConfig(string $title, string $body, ?string $imageUrl = null): WebPushConfig
    {
        $config = [
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => config('app.url') . '/images/logo.png',
                'badge' => config('app.url') . '/images/badge.png',
            ],
            'fcm_options' => [
                'link' => config('app.url'),
            ],
        ];

        if ($imageUrl) {
            $config['notification']['image'] = $imageUrl;
        }

        return WebPushConfig::fromArray($config);
    }

    /**
     * Check if exception indicates an invalid token.
     */
    protected function isInvalidToken(MessagingException $e): bool
    {
        return $this->isInvalidTokenError($e->getMessage());
    }

    /**
     * Check if error message indicates an invalid token.
     */
    protected function isInvalidTokenError(string $message): bool
    {
        $invalidTokenErrors = [
            'not-registered',
            'invalid-registration-token',
            'registration-token-not-registered',
        ];

        $messageLower = strtolower($message);
        foreach ($invalidTokenErrors as $error) {
            if (str_contains($messageLower, $error)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send event reminder notification.
     */
    public function sendEventReminder(User $user, array $eventData): array
    {
        return $this->sendToUser(
            $user,
            "Rappel: {$eventData['title']}",
            "Votre evenement commence dans {$eventData['time_until']}",
            [
                'type' => 'event_reminder',
                'event_id' => (string) $eventData['id'],
                'action' => 'open_event',
            ]
        );
    }

    /**
     * Send task due notification.
     */
    public function sendTaskDue(User $user, array $taskData): array
    {
        return $this->sendToUser(
            $user,
            "Tache due: {$taskData['title']}",
            "La tache \"{$taskData['title']}\" est due aujourd'hui",
            [
                'type' => 'task_due',
                'task_id' => (string) $taskData['id'],
                'event_id' => (string) $taskData['event_id'],
                'action' => 'open_task',
            ]
        );
    }

    /**
     * Send guest RSVP notification.
     */
    public function sendGuestRsvp(User $user, array $guestData): array
    {
        $status = $guestData['status'] === 'confirmed' ? 'confirme' : 'decline';
        return $this->sendToUser(
            $user,
            "RSVP: {$guestData['name']}",
            "{$guestData['name']} a {$status} l'invitation",
            [
                'type' => 'guest_rsvp',
                'guest_id' => (string) $guestData['id'],
                'event_id' => (string) $guestData['event_id'],
                'action' => 'open_guests',
            ]
        );
    }

    /**
     * Send collaboration invite notification.
     */
    public function sendCollaborationInvite(User $user, array $eventData): array
    {
        return $this->sendToUser(
            $user,
            'Nouvelle invitation',
            "Vous avez ete invite a collaborer sur \"{$eventData['title']}\"",
            [
                'type' => 'collaboration_invite',
                'event_id' => (string) $eventData['id'],
                'action' => 'open_collaborations',
            ]
        );
    }
}
