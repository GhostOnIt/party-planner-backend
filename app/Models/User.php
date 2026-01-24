<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'role',
        'is_active',
        'otp_enabled',
        'preferred_otp_channel',
        'password',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['avatar_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'otp_enabled' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Get the avatar URL attribute.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        return '/storage/' . ltrim($this->avatar, '/');
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Check if the user is a regular user.
     */
    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    /**
     * Check if the user account is active.
     */
    public function isActiveAccount(): bool
    {
        return $this->is_active ?? true;
    }

    /**
     * Toggle the active status of the user.
     */
    public function toggleActive(): bool
    {
        $this->is_active = !$this->is_active;
        $this->save();

        return $this->is_active;
    }

    /**
     * Get the events created by the user.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Get the collaborations for the user.
     */
    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    /**
     * Get the pending collaborations for the user (not yet accepted).
     */
    public function pendingCollaborations(): HasMany
    {
        return $this->hasMany(Collaborator::class)->whereNull('accepted_at');
    }

    /**
     * Get the events where the user is a collaborator.
     */
    public function collaboratedEvents(): HasManyThrough
    {
        return $this->hasManyThrough(
            Event::class,
            Collaborator::class,
            'user_id',
            'id',
            'id',
            'event_id'
        );
    }

    /**
     * Get the tasks assigned to the user.
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to_user_id');
    }

    /**
     * Get the photos uploaded by the user.
     */
    public function uploadedPhotos(): HasMany
    {
        return $this->hasMany(Photo::class, 'uploaded_by_user_id');
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the unread notifications for the user.
     */
    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(Notification::class)->whereNull('read_at');
    }

    /**
     * Get the events where the user is a collaborator (alias).
     */
    public function collaboratingEvents(): HasManyThrough
    {
        return $this->collaboratedEvents();
    }

    /**
     * Get the subscriptions for the user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the OTPs for the user.
     */
    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class);
    }

    /**
     * Check if OTP is enabled for the user.
     */
    public function hasOtpEnabled(): bool
    {
        return $this->otp_enabled ?? false;
    }

    /**
     * Get the preferred OTP channel.
     */
    public function getPreferredOtpChannel(): string
    {
        return $this->preferred_otp_channel ?? Otp::CHANNEL_EMAIL;
    }

    /**
     * Get the event types for the user.
     */
    public function eventTypes(): HasMany
    {
        return $this->hasMany(UserEventType::class);
    }

    /**
     * Get the collaborator roles for the user.
     */
    public function collaboratorRoles(): HasMany
    {
        return $this->hasMany(UserCollaboratorRole::class);
    }

    /**
     * Get the budget categories for the user.
     */
    public function budgetCategories(): HasMany
    {
        return $this->hasMany(UserBudgetCategory::class);
    }
}
