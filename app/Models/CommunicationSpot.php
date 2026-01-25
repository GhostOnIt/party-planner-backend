<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationSpot extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'description',
        'image',
        'badge',
        'badge_type',
        'primary_button',
        'secondary_button',
        'poll_question',
        'poll_options',
        'is_active',
        'display_locations',
        'priority',
        'start_date',
        'end_date',
        'target_roles',
        'views',
        'clicks',
        'votes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'primary_button' => 'array',
        'secondary_button' => 'array',
        'poll_options' => 'array',
        'display_locations' => 'array',
        'target_roles' => 'array',
        'votes' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'priority' => 'integer',
        'views' => 'integer',
        'clicks' => 'integer',
    ];

    /**
     * Get the votes for this spot.
     */
    public function userVotes(): HasMany
    {
        return $this->hasMany(CommunicationSpotVote::class, 'spot_id');
    }

    /**
     * Check if a user has voted on this spot.
     */
    public function hasUserVoted(int $userId): bool
    {
        return $this->userVotes()->where('user_id', $userId)->exists();
    }

    /**
     * Get user's vote for this spot.
     */
    public function getUserVote(int $userId): ?CommunicationSpotVote
    {
        return $this->userVotes()->where('user_id', $userId)->first();
    }

    /**
     * Get the computed status.
     */
    public function getStatusAttribute(): string
    {
        $now = now();

        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return 'scheduled';
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Get the stats attribute.
     */
    public function getStatsAttribute(): array
    {
        return [
            'views' => $this->views,
            'clicks' => $this->clicks,
            'votes' => $this->votes ?? [],
        ];
    }

    /**
     * Scope to get active spots for a location.
     */
    public function scopeActiveForLocation($query, string $location, mixed $userRole = null)
    {
        $now = now();

        // Convert role to string if it's an enum
        if ($userRole !== null && !is_string($userRole)) {
            $userRole = $userRole->value ?? (string) $userRole;
        }

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->whereJsonContains('display_locations', $location)
            ->when($userRole, function ($q) use ($userRole) {
                $q->where(function ($query) use ($userRole) {
                    $query->whereNull('target_roles')
                        ->orWhereJsonLength('target_roles', 0)
                        ->orWhereJsonContains('target_roles', $userRole);
                });
            })
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Increment views count.
     */
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    /**
     * Increment clicks count.
     */
    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    /**
     * Record a vote for a poll option.
     */
    public function recordVote(string $optionId): void
    {
        $votes = $this->votes ?? [];
        $votes[$optionId] = ($votes[$optionId] ?? 0) + 1;
        $this->update(['votes' => $votes]);
    }

    /**
     * Transform to API response format.
     */
    public function toApiResponse(): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'badge' => $this->badge,
            'badgeType' => $this->badge_type,
            'primaryButton' => $this->primary_button,
            'secondaryButton' => $this->secondary_button,
            'pollQuestion' => $this->poll_question,
            'pollOptions' => $this->poll_options,
            'isActive' => $this->is_active,
            'displayLocations' => $this->display_locations,
            'priority' => $this->priority,
            'startDate' => $this->start_date?->toIso8601String(),
            'endDate' => $this->end_date?->toIso8601String(),
            'targetRoles' => $this->target_roles ?? [],
            'targetLanguages' => [], // Deprecated but kept for frontend compatibility
            'status' => $this->status,
            'stats' => $this->stats,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
