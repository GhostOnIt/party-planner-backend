<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'type',
        'description',
        'date',
        'time',
        'location',
        'estimated_budget',
        'actual_budget',
        'theme',
        'expected_guests_count',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time' => 'datetime:H:i',
            'estimated_budget' => 'decimal:2',
            'actual_budget' => 'decimal:2',
            'expected_guests_count' => 'integer',
        ];
    }

    /**
     * Get the owner of the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for user relationship.
     */
    public function owner(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Get the guests for the event.
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * Get the tasks for the event.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the budget items for the event.
     */
    public function budgetItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    /**
     * Get the photos for the event.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Get the featured photo (cover image) for the event.
     */
    public function featuredPhoto(): HasOne
    {
        return $this->hasOne(Photo::class)->where('is_featured', true)->latest();
    }

    /**
     * Get the collaborators for the event.
     */
    public function collaborators(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    /**
     * Get the invitations for the event.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Get the notifications for the event.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the subscription for the event.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Check if user can view this event.
     */
    public function canBeViewedBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $this->collaborators()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user can edit this event.
     */
    public function canBeEditedBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->collaborators()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'editor'])
            ->exists();
    }
}
