<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'title',
        'slug',
        'description',
        'price',
        'duration_days',
        'is_trial',
        'is_one_time_use',
        'is_active',
        'limits',
        'features',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_days' => 'integer',
            'is_trial' => 'boolean',
            'is_one_time_use' => 'boolean',
            'is_active' => 'boolean',
            'limits' => 'array',
            'features' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get a specific limit value.
     */
    public function getLimit(string $key, int $default = 0): int
    {
        $limits = $this->limits ?? [];
        return $limits[$key] ?? $default;
    }

    /**
     * Check if a limit is unlimited (-1).
     */
    public function isUnlimited(string $limitKey): bool
    {
        return $this->getLimit($limitKey) === -1;
    }

    /**
     * Check if plan has a specific feature.
     */
    public function hasFeature(string $key): bool
    {
        $features = $this->features ?? [];
        return $features[$key] ?? false;
    }

    /**
     * Get all features as array.
     */
    public function getFeaturesArray(): array
    {
        return $this->features ?? [];
    }

    /**
     * Get all limits as array.
     */
    public function getLimitsArray(): array
    {
        return $this->limits ?? [];
    }

    /**
     * Get the events creation limit per billing period.
     */
    public function getEventsCreationLimit(): int
    {
        return $this->getLimit('events.creations_per_billing_period', 1);
    }

    /**
     * Get max guests per event.
     */
    public function getGuestsLimit(): int
    {
        return $this->getLimit('guests.max_per_event', 10);
    }

    /**
     * Get max collaborators per event.
     */
    public function getCollaboratorsLimit(): int
    {
        return $this->getLimit('collaborators.max_per_event', 1);
    }

    /**
     * Get max photos per event.
     */
    public function getPhotosLimit(): int
    {
        return $this->getLimit('photos.max_per_event', 5);
    }

    /**
     * Scope: only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: trial plans.
     */
    public function scopeTrial($query)
    {
        return $query->where('is_trial', true);
    }

    /**
     * Scope: non-trial plans.
     */
    public function scopePaid($query)
    {
        return $query->where('is_trial', false)->where('price', '>', 0);
    }

    /**
     * Scope: order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === 0) {
            return 'Gratuit';
        }
        return number_format($this->price, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Get duration label.
     */
    public function getDurationLabelAttribute(): string
    {
        if ($this->duration_days === 1) {
            return '1 jour';
        }
        if ($this->duration_days < 30) {
            return $this->duration_days . ' jours';
        }
        if ($this->duration_days === 30) {
            return '1 mois';
        }
        $months = round($this->duration_days / 30);
        return $months . ' mois';
    }
}

