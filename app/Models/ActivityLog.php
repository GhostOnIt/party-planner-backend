<?php

namespace App\Models;

use App\Jobs\StoreActivityLogJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'actor_type',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'source',
        'page_url',
        'session_id',
        'metadata',
        's3_key',
        's3_archived_at',
    ];

    protected $appends = ['resource_name'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            's3_archived_at' => 'datetime',
        ];
    }

    /**
     * Constantes pour les types d'acteur.
     */
    const ACTOR_ADMIN = 'admin';
    const ACTOR_USER = 'user';
    const ACTOR_SYSTEM = 'system';
    const ACTOR_GUEST = 'guest';

    /**
     * Constantes pour les sources.
     */
    const SOURCE_API = 'api';
    const SOURCE_NAVIGATION = 'navigation';
    const SOURCE_UI_INTERACTION = 'ui_interaction';
    const SOURCE_SYSTEM = 'system';

    /**
     * Get the resource name attribute.
     */
    public function getResourceNameAttribute(): ?string
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }

        if (!empty($this->new_values['name'])) {
            return $this->new_values['name'];
        }
        if (!empty($this->new_values['title'])) {
            return $this->new_values['title'];
        }
        if (!empty($this->old_values['name'])) {
            return $this->old_values['name'];
        }
        if (!empty($this->old_values['title'])) {
            return $this->old_values['title'];
        }

        try {
            $modelClass = $this->model_type;
            if (class_exists($modelClass)) {
                $subject = $modelClass::find($this->model_id);
                if ($subject) {
                    return $subject->name ?? $subject->title ?? "#{$this->model_id}";
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return "#{$this->model_id}";
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias pour la rétro-compatibilité avec l'ancien système.
     */
    public function admin(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Get the subject model (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * Static method to create a log entry (synchrone).
     */
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $actorType = null,
        ?string $source = null,
        ?string $pageUrl = null,
        ?string $sessionId = null,
        ?array $metadata = null
    ): self {
        $user = auth()->user();
        $resolvedActorType = $actorType ?? ($user?->isAdmin() ? self::ACTOR_ADMIN : self::ACTOR_USER);

        return self::create([
            'user_id' => $user?->id,
            'actor_type' => $resolvedActorType,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'source' => $source ?? self::SOURCE_API,
            'page_url' => $pageUrl,
            'session_id' => $sessionId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Static method to create a log entry de manière asynchrone via un job.
     * Le log est inséré en base ET écrit sur S3 en arrière-plan.
     */
    public static function logAsync(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $actorType = null,
        ?string $source = null,
        ?string $pageUrl = null,
        ?string $sessionId = null,
        ?array $metadata = null
    ): void {
        $user = auth()->user();
        $resolvedActorType = $actorType ?? ($user?->isAdmin() ? self::ACTOR_ADMIN : self::ACTOR_USER);

        $logData = [
            'user_id' => $user?->id,
            'actor_type' => $resolvedActorType,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'source' => $source ?? self::SOURCE_API,
            'page_url' => $pageUrl,
            'session_id' => $sessionId,
            'metadata' => $metadata,
        ];

        StoreActivityLogJob::dispatch($logData);
    }

    /**
     * Prépare les données d'un log sans les persister (utile pour les batch inserts asynchrones).
     */
    public static function prepareLogData(
        string $action,
        string $description,
        ?int $userId = null,
        ?string $actorType = null,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $source = null,
        ?string $pageUrl = null,
        ?string $sessionId = null,
        ?array $metadata = null
    ): array {
        return [
            'user_id' => $userId ?? auth()->id(),
            'actor_type' => $actorType ?? self::ACTOR_USER,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'source' => $source ?? self::SOURCE_API,
            'page_url' => $pageUrl,
            'session_id' => $sessionId,
            'metadata' => $metadata,
        ];
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * Scope : filtrer par utilisateur (rétro-compatible avec byAdmin).
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Alias rétro-compatible.
     */
    public function scopeByAdmin(Builder $query, int $adminId): Builder
    {
        return $query->where('user_id', $adminId);
    }

    /**
     * Scope : filtrer par type d'acteur (admin, user, system, guest).
     */
    public function scopeByActorType(Builder $query, string $actorType): Builder
    {
        return $query->where('actor_type', $actorType);
    }

    /**
     * Scope : filtrer par source (api, navigation, ui_interaction, system).
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Scope : uniquement les logs provenant du frontend.
     */
    public function scopeFrontend(Builder $query): Builder
    {
        return $query->whereIn('source', [self::SOURCE_NAVIGATION, self::SOURCE_UI_INTERACTION]);
    }

    /**
     * Scope : uniquement les logs provenant du backend (API).
     */
    public function scopeBackend(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_API);
    }

    /**
     * Scope : logs non encore archivés vers S3.
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('s3_archived_at');
    }

    /**
     * Scope : logs déjà archivés vers S3.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('s3_archived_at');
    }

    /**
     * Scope : filtrer par model type et optionnel model ID.
     */
    public function scopeForModel(Builder $query, string $modelType, ?int $modelId = null): Builder
    {
        $query->where('model_type', $modelType);

        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        return $query;
    }

    /**
     * Scope : logs récents triés par date.
     */
    public function scopeRecent(Builder $query, int $limit = 50): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope : filtrer par type d'action.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope : filtrer par plage de dates.
     */
    public function scopeDateBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope : recherche dans la description (case-insensitive).
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('description', 'ilike', "%{$search}%")
              ->orWhere('action', 'ilike', "%{$search}%")
              ->orWhereHas('user', function ($q) use ($search) {
                  $q->where('name', 'ilike', "%{$search}%");
              });
        });
    }

    /**
     * Scope : logs éligibles à l'archivage (plus vieux que X jours, pas encore archivés).
     */
    public function scopeEligibleForArchival(Builder $query, int $daysOld = 30): Builder
    {
        return $query->where('created_at', '<', now()->subDays($daysOld))
                     ->whereNull('s3_archived_at');
    }

    /**
     * Scope : logs éligibles à la purge (archivés et plus vieux que X jours).
     */
    public function scopeEligibleForPurge(Builder $query, int $daysOld = 30): Builder
    {
        return $query->where('created_at', '<', now()->subDays($daysOld))
                     ->whereNotNull('s3_key');
    }
}
