<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'idempotency_key',
        'amount',
        'currency',
        'payment_method',
        'transaction_reference',
        'status',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the subscription that the payment belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Owner of subscription, event owner, or accepted collaborator may access this payment.
     */
    public function canBeAccessedBy(User $user): bool
    {
        $subscription = $this->subscription;
        if (!$subscription) {
            return false;
        }

        if ($subscription->event_id === null) {
            return $subscription->user_id === $user->id;
        }

        $event = $subscription->event;
        if (!$event) {
            return false;
        }

        if ($event->user_id === $user->id) {
            return true;
        }

        return $event->collaborators()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Check if the payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the payment was refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Get the payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'mtn_mobile_money' => 'MTN Mobile Money',
            'airtel_money' => 'Airtel Money',
            default => $this->payment_method,
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'completed' => 'Complété',
            'failed' => 'Échoué',
            'refunded' => 'Remboursé',
            default => $this->status,
        };
    }

    /**
     * Get the formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', ' ') . ' ' . $this->currency;
    }

    /**
     * Mark as completed.
     */
    public function markAsCompleted(?string $reference = null): void
    {
        $this->update([
            'status' => 'completed',
            'transaction_reference' => $reference ?? $this->transaction_reference,
        ]);

        $this->subscription->update([
            'payment_status' => 'paid',
            'status' => 'active',
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'idempotency_key' => null,
        ]);
        $this->subscription->update(['payment_status' => 'failed']);
    }

    /**
     * Mark as refunded.
     */
    /**
     * Failed initiation (validation / provider off) without changing subscription payment_status.
     */
    public function markInitiationFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'idempotency_key' => null,
        ]);
    }

    public function markAsRefunded(?string $reason = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['refund_reason'] = $reason;
        $metadata['refunded_at'] = now()->toIso8601String();

        $this->update([
            'status' => 'refunded',
            'idempotency_key' => null,
            'metadata' => $metadata,
        ]);

        $this->subscription->update(['payment_status' => 'refunded']);
    }

    /**
     * Check if the payment can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->status === 'completed';
    }
}
