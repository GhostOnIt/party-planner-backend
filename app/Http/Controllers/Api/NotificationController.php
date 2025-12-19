<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Display a listing of notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->with('event')
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->filled('unread')) {
            if ($request->boolean('unread')) {
                $query->whereNull('read_at');
            } else {
                $query->whereNotNull('read_at');
            }
        }

        $perPage = min($request->input('per_page', 20), 100);
        $notifications = $query->paginate($perPage);

        return response()->json($notifications);
    }

    /**
     * Get unread count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return response()->json(['count' => $count]);
    }

    /**
     * Get recent notifications.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min($request->input('limit', 5), 20);

        $notifications = Notification::where('user_id', $user->id)
            ->with('event:id,title')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $this->notificationService->markAsRead($notification);

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'message' => 'Toutes les notifications marquées comme lues.',
            'count' => $count,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $this->notificationService->delete($notification);

        return response()->json(['message' => 'Notification supprimée.']);
    }

    /**
     * Bulk delete notifications.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'notifications' => 'required|array',
            'notifications.*' => 'exists:notifications,id',
        ]);

        $count = $this->notificationService->bulkDelete($request->user(), $request->notifications);

        return response()->json([
            'message' => "{$count} notification(s) supprimée(s).",
            'count' => $count,
        ]);
    }

    /**
     * Clear all read notifications.
     */
    public function clearRead(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNotNull('read_at')
            ->delete();

        return response()->json([
            'message' => "{$count} notification(s) supprimée(s).",
            'count' => $count,
        ]);
    }

    /**
     * Get notification settings.
     */
    public function settings(Request $request): JsonResponse
    {
        $preferences = $this->notificationService->getUserPreferences($request->user());

        return response()->json(['preferences' => $preferences]);
    }

    /**
     * Update notification settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_reminder' => 'boolean',
            'guest_reminder' => 'boolean',
            'budget_alert' => 'boolean',
            'event_reminder' => 'boolean',
            'collaboration_invite' => 'boolean',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
        ]);

        $this->notificationService->updatePreferences($request->user(), $validated);

        return response()->json([
            'message' => 'Paramètres mis à jour.',
            'preferences' => $this->notificationService->getUserPreferences($request->user()),
        ]);
    }

    /**
     * Register device token for push notifications.
     */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:ios,android,fcm',
        ]);

        $this->notificationService->registerDeviceToken(
            $request->user(),
            $validated['token'],
            $validated['platform']
        );

        return response()->json(['message' => 'Token enregistré avec succès.']);
    }
}
