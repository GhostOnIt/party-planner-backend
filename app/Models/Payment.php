<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
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

        $this->subscription->update(['payment_status' => 'paid']);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
        $this->subscription->update(['payment_status' => 'failed']);
    }

    /**
     * Mark as refunded.
     */
    public function markAsRefunded(?string $reason = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['refund_reason'] = $reason;
        $metadata['refunded_at'] = now()->toIso8601String();

        $this->update([
            'status' => 'refunded',
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
