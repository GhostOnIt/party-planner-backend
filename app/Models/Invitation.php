<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'guest_id',
        'token',
        'sent_at',
        'opened_at',
        'responded_at',
        'template_id',
        'custom_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invitation $invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(32);
            }
        });
    }

    /**
     * Get the event that the invitation belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the guest that the invitation belongs to.
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * Check if the invitation has been sent.
     */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * Check if the invitation has been opened.
     */
    public function isOpened(): bool
    {
        return $this->opened_at !== null;
    }

    /**
     * Check if the invitation has been responded to.
     */
    public function isResponded(): bool
    {
        return $this->responded_at !== null;
    }

    /**
     * Get the public URL for the invitation.
     */
    public function getPublicUrlAttribute(): string
    {
        return route('invitations.show', $this->token);
    }

    /**
     * Mark the invitation as sent.
     */
    public function markAsSent(): void
    {
        $this->update(['sent_at' => now()]);
    }

    /**
     * Mark the invitation as opened.
     */
    public function markAsOpened(): void
    {
        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }
}
