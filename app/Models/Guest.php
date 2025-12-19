<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'name',
        'email',
        'phone',
        'rsvp_status',
        'checked_in',
        'checked_in_at',
        'invitation_sent_at',
        'invitation_token',
        'reminder_sent_at',
        'notes',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['invitation_url'];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Guest $guest) {
            if (empty($guest->invitation_token)) {
                $guest->invitation_token = Str::random(64);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in' => 'boolean',
            'checked_in_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the event that the guest belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the invitation for the guest.
     */
    public function invitation(): HasOne
    {
        return $this->hasOne(Invitation::class);
    }

    /**
     * Check if the guest has confirmed attendance.
     */
    public function hasConfirmed(): bool
    {
        return $this->rsvp_status === 'accepted';
    }

    /**
     * Check if the guest has declined.
     */
    public function hasDeclined(): bool
    {
        return $this->rsvp_status === 'declined';
    }

    /**
     * Check if the guest response is pending.
     */
    public function isPending(): bool
    {
        return $this->rsvp_status === 'pending';
    }

    /**
     * Check if invitation has been sent.
     */
    public function invitationSent(): bool
    {
        return $this->invitation_sent_at !== null;
    }

    /**
     * Get the public invitation URL.
     */
    public function getInvitationUrlAttribute(): string
    {
        return config('app.frontend_url', config('app.url')) . '/invitation/' . $this->invitation_token;
    }

    /**
     * Generate a new invitation token.
     */
    public function regenerateToken(): string
    {
        $this->invitation_token = Str::random(64);
        $this->save();

        return $this->invitation_token;
    }
}
