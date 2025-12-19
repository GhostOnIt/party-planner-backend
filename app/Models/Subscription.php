<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event_id',
        'plan_type',
        'base_price',
        'guest_count',
        'guest_price_per_unit',
        'total_price',
        'payment_status',
        'payment_method',
        'payment_reference',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'guest_price_per_unit' => 'decimal:2',
            'total_price' => 'decimal:2',
            'guest_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event associated with the subscription.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the payments for the subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if the subscription is active.
     */
    public function isActive(): bool
    {
        return $this->payment_status === 'paid'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Check if the subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the subscription is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if the subscription is pending payment.
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if it's a pro plan.
     */
    public function isPro(): bool
    {
        return $this->plan_type === 'pro';
    }

    /**
     * Check if it's a starter plan.
     */
    public function isStarter(): bool
    {
        return $this->plan_type === 'starter';
    }

    /**
     * Get the plan label.
     */
    public function getPlanLabelAttribute(): string
    {
        return match ($this->plan_type) {
            'starter' => 'Starter',
            'pro' => 'Pro',
            default => $this->plan_type,
        };
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(?string $reason = null): void
    {
        $this->update([
            'payment_status' => 'cancelled',
            'expires_at' => now(),
        ]);
    }

    /**
     * Check if the subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->payment_status === 'cancelled';
    }

    /**
     * Check if the subscription can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->payment_status, ['cancelled', 'refunded']);
    }
}
