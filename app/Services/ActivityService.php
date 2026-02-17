<?php

namespace App\Services;

use App\Jobs\StoreActivityLogJob;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ActivityService
{
    /**
     * Si true, les logs sont dispatchés via un job asynchrone (SQL + S3).
     * Si false, les logs sont écrits directement en base (sans S3).
     */
    protected bool $async = true;

    /**
     * Forcer le mode synchrone (utile pour les tests ou les cas critiques).
     */
    public function sync(): static
    {
        $this->async = false;
        return $this;
    }

    // ============================================================
    // Méthodes de logging génériques
    // ============================================================

    /**
     * Log a generic action.
     */
    public function logAction(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $changes = null,
        ?string $actorType = null,
        ?string $source = null,
        ?string $pageUrl = null,
        ?string $sessionId = null,
        ?array $metadata = null
    ): ?ActivityLog {
        $oldValues = null;
        $newValues = null;

        if ($changes) {
            $oldValues = $changes['old'] ?? null;
            $newValues = $changes['new'] ?? null;
        }

        if ($this->async) {
            ActivityLog::logAsync(
                $action,
                $description,
                $model,
                $oldValues,
                $newValues,
                $actorType,
                $source,
                $pageUrl,
                $sessionId,
                $metadata
            );
            return null;
        }

        return ActivityLog::log(
            $action,
            $description,
            $model,
            $oldValues,
            $newValues,
            $actorType,
            $source,
            $pageUrl,
            $sessionId,
            $metadata
        );
    }

    /**
     * Log user login.
     */
    public function logLogin(User $user): ?ActivityLog
    {
        $isAdmin = $user->isAdmin();

        $logData = [
            'user_id' => $user->id,
            'actor_type' => $isAdmin ? ActivityLog::ACTOR_ADMIN : ActivityLog::ACTOR_USER,
            'action' => 'login',
            'description' => $isAdmin
                ? "Connexion de l'administrateur {$user->name}"
                : "Connexion de l'utilisateur {$user->name}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'source' => ActivityLog::SOURCE_API,
        ];

        if ($this->async) {
            StoreActivityLogJob::dispatch($logData);
            return null;
        }

        return ActivityLog::create($logData);
    }

    /**
     * Log user logout.
     */
    public function logLogout(User $user): ?ActivityLog
    {
        $isAdmin = $user->isAdmin();

        $logData = [
            'user_id' => $user->id,
            'actor_type' => $isAdmin ? ActivityLog::ACTOR_ADMIN : ActivityLog::ACTOR_USER,
            'action' => 'logout',
            'description' => $isAdmin
                ? "Déconnexion de l'administrateur {$user->name}"
                : "Déconnexion de l'utilisateur {$user->name}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'source' => ActivityLog::SOURCE_API,
        ];

        if ($this->async) {
            StoreActivityLogJob::dispatch($logData);
            return null;
        }

        return ActivityLog::create($logData);
    }

    // ============================================================
    // Méthodes de logging admin (rétro-compatibles)
    // ============================================================

    /**
     * Log user-related action (admin).
     */
    public function logUserAction(string $action, User $targetUser, ?array $changes = null): ?ActivityLog
    {
        $descriptions = [
            'view' => "Consultation du profil de {$targetUser->name}",
            'create' => "Création de l'utilisateur {$targetUser->name}",
            'update' => "Modification de l'utilisateur {$targetUser->name}",
            'update_role' => "Changement de rôle de {$targetUser->name}",
            'delete' => "Suppression de l'utilisateur {$targetUser->name}",
            'toggle_active' => $targetUser->is_active
                ? "Activation de l'utilisateur {$targetUser->name}"
                : "Désactivation de l'utilisateur {$targetUser->name}",
        ];

        return $this->logAction(
            $action,
            $descriptions[$action] ?? "Action '{$action}' sur {$targetUser->name}",
            $targetUser,
            $changes,
            ActivityLog::ACTOR_ADMIN
        );
    }

    /**
     * Log event-related action (admin).
     */
    public function logEventAction(string $action, Event $event, ?array $changes = null): ?ActivityLog
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
            $changes,
            ActivityLog::ACTOR_ADMIN
        );
    }

    /**
     * Log template-related action (admin).
     */
    public function logTemplateAction(string $action, EventTemplate $template, ?array $changes = null): ?ActivityLog
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
            $changes,
            ActivityLog::ACTOR_ADMIN
        );
    }

    /**
     * Log subscription-related action (admin).
     */
    public function logSubscriptionAction(string $action, Subscription $subscription, ?array $changes = null): ?ActivityLog
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
            $changes,
            ActivityLog::ACTOR_ADMIN
        );
    }

    // ============================================================
    // Méthodes de logging utilisateur (nouvelles)
    // ============================================================

    /**
     * Log une action utilisateur sur un événement.
     */
    public function logUserEventAction(string $action, Event $event, ?User $user = null, ?array $changes = null): ?ActivityLog
    {
        $user = $user ?? auth()->user();

        $descriptions = [
            'create' => "Création de l'événement {$event->title}",
            'update' => "Modification de l'événement {$event->title}",
            'delete' => "Suppression de l'événement {$event->title}",
            'view' => "Consultation de l'événement {$event->title}",
            'duplicate' => "Duplication de l'événement {$event->title}",
        ];

        return $this->logAction(
            $action,
            $descriptions[$action] ?? "Action '{$action}' sur l'événement {$event->title}",
            $event,
            $changes,
            ActivityLog::ACTOR_USER
        );
    }

    /**
     * Log une action utilisateur sur son profil.
     */
    public function logProfileAction(string $action, User $user, ?array $changes = null): ?ActivityLog
    {
        $descriptions = [
            'update' => "Modification du profil par {$user->name}",
            'update_password' => "Changement de mot de passe par {$user->name}",
            'update_avatar' => "Modification de l'avatar par {$user->name}",
            'delete_avatar' => "Suppression de l'avatar par {$user->name}",
        ];

        return $this->logAction(
            $action,
            $descriptions[$action] ?? "Action '{$action}' sur le profil de {$user->name}",
            $user,
            $changes,
            ActivityLog::ACTOR_USER
        );
    }

    /**
     * Log un batch d'événements frontend (navigation, UI interactions).
     *
     * Les logs sont insérés en SQL via batch insert pour la performance,
     * puis chaque log est envoyé individuellement vers S3 si le mode async est actif.
     */
    public function logFrontendBatch(array $events, User $user): int
    {
        $logs = [];
        $now = now();

        foreach ($events as $event) {
            $source = $event['type'] ?? ActivityLog::SOURCE_NAVIGATION;
            $action = $event['action'] ?? 'page_view';

            $logs[] = [
                'user_id' => $user->id,
                'actor_type' => $user->isAdmin() ? ActivityLog::ACTOR_ADMIN : ActivityLog::ACTOR_USER,
                'action' => $action,
                'description' => $event['description'] ?? $this->buildFrontendDescription($source, $action, $event),
                'model_type' => null,
                'model_id' => null,
                'old_values' => null,
                'new_values' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'source' => $source,
                'page_url' => $event['page_url'] ?? null,
                'session_id' => $event['session_id'] ?? null,
                'metadata' => isset($event['metadata']) ? json_encode($event['metadata']) : null,
                'created_at' => $event['timestamp'] ?? $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($logs)) {
            // Batch insert en SQL pour la performance
            ActivityLog::insert($logs);

            // L'écriture S3 sera gérée par le job ArchiveAndPurgeLogsJob
            // (les logs frontend sont traités en masse plutôt qu'un par un)
        }

        return count($logs);
    }

    /**
     * Construit une description pour un événement frontend.
     */
    protected function buildFrontendDescription(string $source, string $action, array $event): string
    {
        if ($source === ActivityLog::SOURCE_NAVIGATION) {
            $url = $event['page_url'] ?? 'page inconnue';
            return "Navigation vers {$url}";
        }

        if ($source === ActivityLog::SOURCE_UI_INTERACTION) {
            $element = $event['metadata']['element'] ?? 'élément';
            return "Interaction UI : {$action} sur {$element}";
        }

        return "Action frontend : {$action}";
    }

    // ============================================================
    // Méthodes de requête
    // ============================================================

    /**
     * Get activity logs for a specific user (rétro-compatible avec getActivityForAdmin).
     */
    public function getActivityForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return ActivityLog::byUser($userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Alias rétro-compatible.
     */
    public function getActivityForAdmin(int $adminId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getActivityForUser($adminId, $perPage);
    }

    /**
     * Get recent activity logs.
     */
    public function getRecentActivity(int $limit = 50): Collection
    {
        return ActivityLog::with('user')
            ->recent($limit)
            ->get();
    }

    /**
     * Get activity statistics (enrichies pour le nouveau système).
     */
    public function getActivityStats(?string $actorType = null, ?string $source = null): array
    {
        $baseQuery = ActivityLog::query();

        if ($actorType) {
            $baseQuery->byActorType($actorType);
        }
        if ($source) {
            $baseQuery->bySource($source);
        }

        $total = (clone $baseQuery)->count();
        $today = (clone $baseQuery)->whereDate('created_at', today())->count();
        $thisWeek = (clone $baseQuery)->where('created_at', '>=', now()->startOfWeek())->count();
        $thisMonth = (clone $baseQuery)->where('created_at', '>=', now()->startOfMonth())->count();

        $byAction = (clone $baseQuery)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $byModelType = (clone $baseQuery)
            ->whereNotNull('model_type')
            ->selectRaw('model_type, COUNT(*) as count')
            ->groupBy('model_type')
            ->pluck('count', 'model_type')
            ->map(fn($count, $type) => [
                'type' => class_basename($type),
                'count' => $count,
            ])
            ->values()
            ->toArray();

        $byActorType = (clone $baseQuery)
            ->selectRaw('actor_type, COUNT(*) as count')
            ->groupBy('actor_type')
            ->pluck('count', 'actor_type')
            ->toArray();

        $bySource = (clone $baseQuery)
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        $byUser = ActivityLog::with('user:id,name')
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'user_id' => $row->user_id,
                'user_name' => $row->user?->name ?? 'Inconnu',
                'count' => $row->count,
            ])
            ->toArray();

        $recentUsers = ActivityLog::with('user:id,name')
            ->selectRaw('user_id, MAX(created_at) as last_activity')
            ->groupBy('user_id')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(5)
            ->get()
            ->map(fn($row) => [
                'user_id' => $row->user_id,
                'name' => $row->user?->name ?? 'Inconnu',
                'last_activity' => $row->last_activity,
            ])
            ->toArray();

        return [
            'total' => $total,
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'by_action' => $byAction,
            'by_model_type' => $byModelType,
            'by_actor_type' => $byActorType,
            'by_source' => $bySource,
            'by_user' => $byUser,
            'recent_users' => $recentUsers,
            // Rétro-compatibilité
            'by_admin' => collect($byUser)->map(fn($item) => [
                'admin' => $item['user_name'],
                'count' => $item['count'],
            ])->toArray(),
            'recent_admins' => collect($recentUsers)->pluck('name')->toArray(),
        ];
    }

    /**
     * Get paginated activity logs with filters (enrichi).
     */
    public function getActivityLogs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ActivityLog::with(['user', 'subject']);

        // Filtre par utilisateur (rétro-compatible avec admin_id)
        $userId = $filters['user_id'] ?? $filters['admin_id'] ?? null;
        if (!empty($userId)) {
            $query->byUser($userId);
        }

        if (!empty($filters['actor_type'])) {
            $query->byActorType($filters['actor_type']);
        }

        if (!empty($filters['source'])) {
            $query->bySource($filters['source']);
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

        if (!empty($filters['session_id'])) {
            $query->where('session_id', $filters['session_id']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }
}
