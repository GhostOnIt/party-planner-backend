<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AdminActivityService
{
    /**
     * Log a generic action.
     */
    public function logAction(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $changes = null
    ): AdminActivityLog {
        $oldValues = null;
        $newValues = null;

        if ($changes) {
            $oldValues = $changes['old'] ?? null;
            $newValues = $changes['new'] ?? null;
        }

        return AdminActivityLog::log($action, $description, $model, $oldValues, $newValues);
    }

    /**
     * Log admin login.
     */
    public function logLogin(User $admin): AdminActivityLog
    {
        return AdminActivityLog::create([
            'admin_id' => $admin->id,
            'action' => 'login',
            'description' => "Connexion de l'administrateur {$admin->name}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log user-related action.
     */
    public function logUserAction(string $action, User $targetUser, ?array $changes = null): AdminActivityLog
    {
        $descriptions = [
            'view' => "Consultation du profil de {$targetUser->name}",
            'create' => "Création de l'utilisateur {$targetUser->name}",
            'update' => "Modification de l'utilisateur {$targetUser->name}",
            'update_role' => "Changement de rôle de {$targetUser->name}",
            'delete' => "Suppression de l'utilisateur {$targetUser->name}",
        ];

        return $this->logAction(
            $action,
            $descriptions[$action] ?? "Action '{$action}' sur {$targetUser->name}",
            $targetUser,
            $changes
        );
    }

    /**
     * Log event-related action.
     */
    public function logEventAction(string $action, Event $event, ?array $changes = null): AdminActivityLog
    {
        $descriptions = [
            'view' => "Consultation de l'événement {$event->title}",
            'create' => "Création de l'événement {$event->title}",
            'update' => "Modification de l'événement {$event->title}",
            'delete' => "Suppression de l'événement {$event->title}",
        ];

        return $this->logAction(
            $action,
            $descriptions[$action] ?? "Action '{$action}' sur {$event->title}",
            $event,
            $changes
        );
    }

    /**
     * Log template-related action.
     */
    public function logTemplateAction(string $action, EventTemplate $template, ?array $changes = null): AdminActivityLog
    {
        $descriptions = [
            'view' => "Consultation du template {$template->name}",
            'create' => "Création du template {$template->name}",
            'update' => "Modification du template {$template->name}",
            'delete' => "Suppression du template {$template->name}",
            'toggle_active' => $template->is_active
                ? "Activation du template {$template->name}"
                : "Désactivation du template {$template->name}",
        ];

        return $this->logAction(
            $action,
            $descriptions[$action] ?? "Action '{$action}' sur {$template->name}",
            $template,
            $changes
        );
    }

    /**
     * Log subscription-related action.
     */
    public function logSubscriptionAction(string $action, Subscription $subscription, ?array $changes = null): AdminActivityLog
    {
        $userName = $subscription->event?->user?->name ?? 'Utilisateur #' . $subscription->user_id;
        $eventTitle = $subscription->event?->title ?? 'Événement #' . $subscription->event_id;
        
        $descriptions = [
            'view' => "Consultation de l'abonnement de {$userName}",
            'extend' => "Prolongation de l'abonnement de {$userName} pour {$eventTitle}",
            'change_plan' => "Changement de plan de l'abonnement de {$userName} pour {$eventTitle}",
            'cancel' => "Annulation de l'abonnement de {$userName} pour {$eventTitle}",
        ];

        return $this->logAction(
            $action,
            $descriptions[$action] ?? "Action '{$action}' sur l'abonnement de {$userName}",
            $subscription,
            $changes
        );
    }

    /**
     * Get activity logs for a specific admin.
     */
    public function getActivityForAdmin(int $adminId, int $perPage = 15): LengthAwarePaginator
    {
        return AdminActivityLog::byAdmin($adminId)
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get recent activity logs.
     */
    public function getRecentActivity(int $limit = 50): Collection
    {
        return AdminActivityLog::with('admin')
            ->recent($limit)
            ->get();
    }

    /**
     * Get activity statistics.
     */
    public function getActivityStats(): array
    {
        $logs = AdminActivityLog::query();

        return [
            'total' => $logs->count(),
            'today' => AdminActivityLog::whereDate('created_at', today())->count(),
            'this_week' => AdminActivityLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => AdminActivityLog::where('created_at', '>=', now()->startOfMonth())->count(),
            'by_action' => AdminActivityLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray(),
            'by_model_type' => AdminActivityLog::whereNotNull('model_type')
                ->selectRaw('model_type, COUNT(*) as count')
                ->groupBy('model_type')
                ->pluck('count', 'model_type')
                ->map(fn($count, $type) => [
                    'type' => class_basename($type),
                    'count' => $count,
                ])
                ->values()
                ->toArray(),
            'by_admin' => AdminActivityLog::with('admin:id,name')
                ->selectRaw('admin_id, COUNT(*) as count')
                ->groupBy('admin_id')
                ->get()
                ->map(fn($row) => [
                    'admin' => $row->admin?->name ?? 'Unknown',
                    'count' => $row->count,
                ])
                ->toArray(),
            'recent_admins' => AdminActivityLog::with('admin:id,name')
                ->selectRaw('admin_id, MAX(created_at) as last_activity')
                ->groupBy('admin_id')
                ->orderByRaw('MAX(created_at) DESC')
                ->limit(5)
                ->get()
                ->pluck('admin.name')
                ->toArray(),
        ];
    }

    /**
     * Get paginated activity logs with filters.
     */
    public function getActivityLogs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AdminActivityLog::with(['admin', 'subject']);

        if (!empty($filters['admin_id'])) {
            $query->byAdmin($filters['admin_id']);
        }

        if (!empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (!empty($filters['model_type'])) {
            $query->forModel($filters['model_type']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'] . ' 23:59:59');
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }
}
