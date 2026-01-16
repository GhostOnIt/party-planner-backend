<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlanService
{
    /**
     * Get all plans.
     */
    public function getAll(): Collection
    {
        return Plan::ordered()->get();
    }

    /**
     * Get active plans only.
     * Filters out one-time-use plans that the user has already used.
     */
    public function getActive(?\App\Models\User $user = null): Collection
    {
        $plans = Plan::active()->ordered()->get();

        // Filter out one-time-use plans that user has already used
        if ($user) {
            $plans = $plans->filter(function (Plan $plan) use ($user) {
                if (!$plan->is_one_time_use) {
                    return true; // Not one-time-use, always show
                }

                // Check if user has already used this plan
                return !$this->hasUserUsedPlan($user, $plan);
            });
        }

        return $plans;
    }

    /**
     * Check if user has already used a one-time-use plan.
     */
    public function hasUserUsedPlan(\App\Models\User $user, Plan $plan): bool
    {
        if (!$plan->is_one_time_use) {
            return false; // Not a one-time-use plan
        }

        // Check if user has any subscription (past or present) for this plan
        return $user->subscriptions()
            ->where('plan_id', $plan->id)
            ->exists();
    }

    /**
     * Get available trial plan for user (if not already used).
     * If multiple trials exist, returns the first available one (by sort_order).
     */
    public function getAvailableTrialPlan(?\App\Models\User $user = null): ?Plan
    {
        // Get all active trial plans, ordered by sort_order
        $trialPlans = Plan::active()
            ->trial()
            ->ordered()
            ->get();

        if ($trialPlans->isEmpty()) {
            return null;
        }

        // If no user provided, return the first trial (highest priority)
        if (!$user) {
            return $trialPlans->first();
        }

        // Find the first trial that the user hasn't used yet
        foreach ($trialPlans as $trialPlan) {
            if (!$this->hasUserUsedPlan($user, $trialPlan)) {
                return $trialPlan;
            }
        }

        // All trials have been used
        return null;
    }

    /**
     * Get plan by ID.
     */
    public function getById(int $id): ?Plan
    {
        return Plan::find($id);
    }

    /**
     * Get plan by slug.
     */
    public function getBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    /**
     * Get the trial plan.
     */
    public function getTrialPlan(): ?Plan
    {
        return Plan::active()->trial()->first();
    }

    /**
     * Get the default plan (usually trial or cheapest).
     */
    public function getDefaultPlan(): ?Plan
    {
        // First try to get trial plan
        $trial = $this->getTrialPlan();
        if ($trial) {
            return $trial;
        }

        // Otherwise get cheapest active plan
        return Plan::active()->orderBy('price')->first();
    }

    /**
     * Create a new plan.
     */
    public function create(array $data): Plan
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Ensure unique slug
        $data['slug'] = $this->ensureUniqueSlug($data['slug']);

        return Plan::create($data);
    }

    /**
     * Update a plan.
     */
    public function update(Plan $plan, array $data): Plan
    {
        // If name changed and slug not provided, regenerate slug
        if (isset($data['name']) && !isset($data['slug'])) {
            $newSlug = Str::slug($data['name']);
            if ($newSlug !== $plan->slug) {
                $data['slug'] = $this->ensureUniqueSlug($newSlug, $plan->id);
            }
        }

        $plan->update($data);

        return $plan->fresh();
    }

    /**
     * Delete a plan.
     */
    public function delete(Plan $plan): bool
    {
        // Check if plan has active subscriptions
        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            throw new \Exception('Cannot delete plan with active subscriptions.');
        }

        return $plan->delete();
    }

    /**
     * Toggle plan active status.
     */
    public function toggleActive(Plan $plan): Plan
    {
        $plan->update(['is_active' => !$plan->is_active]);

        return $plan->fresh();
    }

    /**
     * Ensure slug is unique.
     */
    protected function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Plan::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
    }

    /**
     * Get plan comparison data for frontend.
     */
    public function getPlanComparison(): array
    {
        $plans = $this->getActive();

        return $plans->map(function (Plan $plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => $plan->price,
                'formatted_price' => $plan->formatted_price,
                'duration_days' => $plan->duration_days,
                'duration_label' => $plan->duration_label,
                'is_trial' => $plan->is_trial,
                'limits' => $plan->limits,
                'features' => $plan->features,
                'events_limit' => $plan->getEventsCreationLimit(),
                'guests_limit' => $plan->getGuestsLimit(),
                'collaborators_limit' => $plan->getCollaboratorsLimit(),
            ];
        })->toArray();
    }
}

