<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'identifier',
        'code',
        'type',
        'channel',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * OTP types
     */
    public const TYPE_REGISTRATION = 'registration';
    public const TYPE_LOGIN = 'login';
    public const TYPE_PASSWORD_RESET = 'password_reset';

    /**
     * OTP channels
     */
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    /**
     * Maximum verification attempts
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * OTP expiration time in minutes
     */
    public const EXPIRATION_MINUTES = 5;

    /**
     * Get the user that owns the OTP.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the OTP is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if the OTP has exceeded max attempts.
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Check if the OTP is still valid (not expired, not verified, not exceeded attempts).
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isVerified() && !$this->hasExceededAttempts();
    }

    /**
     * Increment the attempts counter.
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Mark the OTP as verified.
     */
    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }

    /**
     * Scope to get valid (non-expired, non-verified) OTPs.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    /**
     * Scope to get OTPs by identifier and type.
     */
    public function scopeForIdentifier($query, string $identifier, string $type)
    {
        return $query->where('identifier', $identifier)->where('type', $type);
    }

    /**
     * Get remaining time before expiration in seconds.
     */
    public function getRemainingTimeAttribute(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Get available OTP types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_REGISTRATION,
            self::TYPE_LOGIN,
            self::TYPE_PASSWORD_RESET,
        ];
    }

    /**
     * Get available OTP channels.
     */
    public static function getChannels(): array
    {
        return [
            self::CHANNEL_EMAIL,
            self::CHANNEL_SMS,
            self::CHANNEL_WHATSAPP,
        ];
    }
}
