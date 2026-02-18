<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasFactory, HasUuids;

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
        'plus_one',
        'plus_one_name',
        'checked_in',
        'checked_in_at',
        'invitation_sent_at',
        'invitation_token',
        'photo_upload_token',
        'reminder_sent_at',
        'notes',
        'dietary_restrictions',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['invitation_url', 'photo_upload_url'];

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
            // Generate photo upload token if guest is checked in
            if ($guest->checked_in && empty($guest->photo_upload_token)) {
                $guest->photo_upload_token = Str::random(64);
            }
        });

        static::updating(function (Guest $guest) {
            // Generate photo upload token if guest becomes checked in
            if ($guest->isDirty('checked_in') && $guest->checked_in && empty($guest->photo_upload_token)) {
                $guest->photo_upload_token = Str::random(64);
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
            'plus_one' => 'boolean',
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

    /**
     * Get the public photo upload URL.
     */
    public function getPhotoUploadUrlAttribute(): ?string
    {
        if (!$this->photo_upload_token || !$this->event_id) {
            return null;
        }

        return config('app.frontend_url', config('app.url')) . '/upload-photo/' . $this->event_id . '/' . $this->photo_upload_token;
    }

    /**
     * Generate a new photo upload token.
     */
    public function regeneratePhotoUploadToken(): string
    {
        $this->photo_upload_token = Str::random(64);
        $this->save();

        return $this->photo_upload_token;
    }
}
