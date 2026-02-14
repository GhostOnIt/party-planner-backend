<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
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
