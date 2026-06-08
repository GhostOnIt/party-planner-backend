<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'uploaded_by_user_id',
        'type',
        'url',
        'thumbnail_url',
        'description',
        'is_featured',
        'moderation_status',
        'moderated_by_user_id',
        'moderated_at',
        'moderation_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * Get the event that the photo belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who uploaded the photo.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    public function isPendingModeration(): bool
    {
        return $this->moderation_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->moderation_status === 'approved';
    }

    /**
     * Check if the photo is a moodboard photo.
     */
    public function isMoodboard(): bool
    {
        return $this->type === 'moodboard';
    }

    /**
     * Check if the photo is an event photo.
     */
    public function isEventPhoto(): bool
    {
        return $this->type === 'event_photo';
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(): void
    {
        $this->update(['is_featured' => !$this->is_featured]);
    }
}
