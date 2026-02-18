<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminActivityLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'admin_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $appends = ['resource_name'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Get the resource name attribute.
     */
    public function getResourceNameAttribute(): ?string
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }

        // Try to get name from new_values first (useful for created resources)
        if (!empty($this->new_values['name'])) {
            return $this->new_values['name'];
        }
        if (!empty($this->new_values['title'])) {
            return $this->new_values['title'];
        }
        // Fall back to old_values (useful for deleted resources)
        if (!empty($this->old_values['name'])) {
            return $this->old_values['name'];
        }
        if (!empty($this->old_values['title'])) {
            return $this->old_values['title'];
        }

        // Manually load the subject model
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
     * Get the admin who performed the action.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the subject model (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * Static method to create a log entry.
     */
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        $admin = auth()->user();

        return self::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Scope to filter by admin.
     */
    public function scopeByAdmin(Builder $query, int $adminId): Builder
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * Scope to filter by model type and optional model ID.
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
     * Scope to get recent logs ordered by date.
     */
    public function scopeRecent(Builder $query, int $limit = 50): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope to filter by action type.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope to search in description (case-insensitive).
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('description', 'ilike', "%{$search}%")
              ->orWhere('action', 'ilike', "%{$search}%")
              ->orWhereHas('admin', function ($q) use ($search) {
                  $q->where('name', 'ilike', "%{$search}%");
              });
        });
    }
}
