<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Display a listing of notifications.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Notification::where('user_id', $user->id)
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

        $notifications = $query->paginate(20);
        $unreadCount = $this->notificationService->getUnreadCount($user);
        $stats = $this->notificationService->getStatistics($user);

        return view('notifications.index', compact('notifications', 'unreadCount', 'stats'));
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): RedirectResponse|JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->notificationService->markAsRead($notification);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Notification marquée comme lue.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count]);
        }

        return redirect()->back()->with('success', 'Toutes les notifications marquées comme lues.');
    }

    /**
     * Display notification settings.
     */
    public function settings(Request $request): View
    {
        $user = $request->user();
        $preferences = $this->notificationService->getUserPreferences($user);

        return view('notifications.settings', compact('preferences'));
    }

    /**
     * Update notification settings.
     */
    public function updateSettings(Request $request): RedirectResponse
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

        // Convert checkbox values to boolean
        $preferences = [];
        foreach ($validated as $key => $value) {
            $preferences[$key] = (bool) $value;
        }

        $this->notificationService->updatePreferences($request->user(), $preferences);

        return redirect()
            ->route('notifications.settings')
            ->with('success', 'Paramètres mis à jour avec succès.');
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, Notification $notification): RedirectResponse|JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->notificationService->delete($notification);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Notification supprimée.');
    }

    /**
     * Bulk delete notifications.
     */
    public function bulkDelete(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'notifications' => 'required|array',
            'notifications.*' => 'exists:notifications,id',
        ]);

        $count = $this->notificationService->bulkDelete($request->user(), $request->notifications);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count]);
        }

        return redirect()->back()->with('success', "{$count} notification(s) supprimée(s).");
    }

    /**
     * Get unread count (for polling/AJAX).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return response()->json(['count' => $count]);
    }

    /**
     * Get recent notifications (for dropdown).
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 5);

        $notifications = Notification::where('user_id', $user->id)
            ->with('event:id,title')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $unreadCount = $this->notificationService->getUnreadCount($user);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Clear all read notifications.
     */
    public function clearRead(Request $request): RedirectResponse|JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNotNull('read_at')
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count]);
        }

        return redirect()->back()->with('success', "{$count} notification(s) supprimée(s).");
    }
}
