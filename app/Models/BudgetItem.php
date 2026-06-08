<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetItem extends Model
{
    use HasFactory, HasUuids;

    protected $appends = [
        'total_paid',
        'remaining_amount',
        'payment_status',
        'attachments_count',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'task_id',
        'category',
        'name',
        'estimated_cost',
        'actual_cost',
        'paid',
        'payment_date',
        'vendor_name',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'paid' => 'boolean',
            'payment_date' => 'date',
        ];
    }

    /**
     * Get the event that the budget item belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the task that this budget item is associated with (if any).
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BudgetItemPayment::class);
    }

    public function paymentAttachments(): HasMany
    {
        return $this->hasMany(BudgetPaymentAttachment::class);
    }

    /**
     * Check if this budget item is linked to a task.
     */
    public function isLinkedToTask(): bool
    {
        return $this->task_id !== null;
    }

    /**
     * Get the difference between estimated and actual cost.
     */
    public function getDifferenceAttribute(): float
    {
        return ($this->actual_cost ?? 0) - ($this->estimated_cost ?? 0);
    }

    /**
     * Check if the item is over budget.
     */
    public function isOverBudget(): bool
    {
        if (!$this->estimated_cost || !$this->actual_cost) {
            return false;
        }

        return $this->actual_cost > $this->estimated_cost;
    }

    public function getTotalPaidAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            $paymentsTotal = (float) $this->payments->sum('amount');
            if ($paymentsTotal > 0 || $this->payments->isNotEmpty()) {
                return $paymentsTotal;
            }
        }

        $paymentsTotal = (float) $this->payments()->sum('amount');

        if ($paymentsTotal > 0 || $this->payments()->exists()) {
            return $paymentsTotal;
        }

        return $this->paid ? (float) ($this->actual_cost ?? 0) : 0.0;
    }

    public function getRemainingAmountAttribute(): float
    {
        return max((float) ($this->actual_cost ?? 0) - $this->total_paid, 0);
    }

    public function getPaymentStatusAttribute(): string
    {
        $actualCost = (float) ($this->actual_cost ?? 0);

        if ($this->total_paid <= 0) {
            return 'unpaid';
        }

        if ($actualCost > 0 && $this->total_paid < $actualCost) {
            return 'partially_paid';
        }

        return 'paid';
    }

    public function getAttachmentsCountAttribute(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->sum(fn ($payment) => $payment->attachments->count());
        }

        return $this->paymentAttachments()->count();
    }

    /**
     * Get the category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'location' => 'Lieu',
            'catering' => 'Traiteur',
            'decoration' => 'Décoration',
            'entertainment' => 'Animation',
            'photography' => 'Photographie',
            'transportation' => 'Transport',
            'other' => 'Autre',
            default => $this->category,
        };
    }
}
