<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminPlanController extends Controller
{
    public function __construct(
        protected PlanService $planService
    ) {}

    /**
     * Display a listing of plans.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Plan::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Filter by trial
        if ($request->has('is_trial')) {
            $query->where('is_trial', $request->boolean('is_trial'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $plans = $query->ordered()->get();

        // Add statistics to each plan
        $plans = $plans->map(function ($plan) {
            $plan->active_subscriptions_count = $plan->subscriptions()
                ->where(function ($q) {
                    $q->where('status', 'active')
                      ->orWhere('status', 'trial')
                      ->orWhere('payment_status', 'paid');
                })
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->count();
            
            $plan->total_subscriptions_count = $plan->subscriptions()->count();
            
            return $plan;
        });

        return response()->json([
            'data' => $plans,
            'meta' => [
                'total' => $plans->count(),
            ],
        ]);
    }

    /**
     * Store a newly created plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'is_trial' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'limits' => ['nullable', 'array'],
            'limits.events.creations_per_billing_period' => ['nullable', 'integer'],
            'limits.guests.max_per_event' => ['nullable', 'integer'],
            'limits.collaborators.max_per_event' => ['nullable', 'integer'],
            'limits.photos.max_per_event' => ['nullable', 'integer'],
            'features' => ['nullable', 'array'],
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Ensure unique slug
        $counter = 1;
        $originalSlug = $validated['slug'];
        while (Plan::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $plan = Plan::create($validated);

        return response()->json([
            'message' => 'Plan créé avec succès.',
            'data' => $plan,
        ], 201);
    }

    /**
     * Display the specified plan.
     */
    public function show(Plan $plan): JsonResponse
    {
        $plan->load('subscriptions');
        
        $plan->active_subscriptions_count = $plan->subscriptions()
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere('status', 'trial')
                  ->orWhere('payment_status', 'paid');
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();

        return response()->json([
            'data' => $plan,
        ]);
    }

    /**
     * Update the specified plan.
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'is_trial' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'limits' => ['nullable', 'array'],
            'limits.events.creations_per_billing_period' => ['nullable', 'integer'],
            'limits.guests.max_per_event' => ['nullable', 'integer'],
            'limits.collaborators.max_per_event' => ['nullable', 'integer'],
            'limits.photos.max_per_event' => ['nullable', 'integer'],
            'features' => ['nullable', 'array'],
        ]);

        $plan->update($validated);

        return response()->json([
            'message' => 'Plan mis à jour avec succès.',
            'data' => $plan->fresh(),
        ]);
    }

    /**
     * Remove the specified plan.
     */
    public function destroy(Plan $plan): JsonResponse
    {
        // Check if plan has active subscriptions
        $activeSubscriptions = $plan->subscriptions()
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere('status', 'trial')
                  ->orWhere('payment_status', 'paid');
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($activeSubscriptions > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer un plan avec des abonnements actifs.',
                'active_subscriptions' => $activeSubscriptions,
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan supprimé avec succès.',
        ]);
    }

    /**
     * Toggle plan active status.
     */
    public function toggleActive(Plan $plan): JsonResponse
    {
        $plan->update(['is_active' => !$plan->is_active]);

        return response()->json([
            'message' => $plan->is_active 
                ? 'Plan activé avec succès.' 
                : 'Plan désactivé avec succès.',
            'data' => $plan->fresh(),
        ]);
    }

    /**
     * Get public plans for pricing page.
     * Filters out one-time-use plans that the user has already used.
     * Calculates popular plan based on active subscriptions statistics.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $plans = $this->planService->getActive($user);

        // Get subscription statistics per plan (only for paid plans, excluding trials)
        $subscriptionStats = \App\Models\Subscription::whereNotNull('plan_id')
            ->where(function ($query) {
                $query->where('status', 'active')
                      ->orWhere('payment_status', 'paid');
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->selectRaw('plan_id, COUNT(*) as subscriptions_count')
            ->groupBy('plan_id')
            ->pluck('subscriptions_count', 'plan_id')
            ->toArray();

        // Find the plan with most active subscriptions (popular plan)
        // Exclude trial plans from popular calculation
        $popularPlanId = null;
        $maxSubscriptions = 0;
        foreach ($subscriptionStats as $planId => $count) {
            $plan = $plans->firstWhere('id', $planId);
            // Only consider paid plans (not trials) for popularity
            if ($plan && !$plan->is_trial && $count > $maxSubscriptions) {
                $maxSubscriptions = $count;
                $popularPlanId = $planId;
            }
        }

        $plansArray = $plans->map(function ($plan) use ($popularPlanId) {
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
                'is_one_time_use' => $plan->is_one_time_use,
                'is_active' => $plan->is_active,
                'is_popular' => $plan->id === $popularPlanId,
                'limits' => $plan->limits,
                'features' => $plan->features,
            ];
        })->values()->toArray(); // Convert Collection to array and reindex

        return response()->json([
            'data' => $plansArray,
        ]);
    }

    /**
     * Get available trial plan for current user.
     */
    public function getAvailableTrial(Request $request): JsonResponse
    {
        $user = $request->user();
        $trialPlan = $this->planService->getAvailableTrialPlan($user);

        if (!$trialPlan) {
            return response()->json([
                'data' => null,
                'available' => false,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $trialPlan->id,
                'name' => $trialPlan->name,
                'slug' => $trialPlan->slug,
                'description' => $trialPlan->description,
                'price' => $trialPlan->price,
                'duration_days' => $trialPlan->duration_days,
                'duration_label' => $trialPlan->duration_label,
                'limits' => $trialPlan->limits,
                'features' => $trialPlan->features,
            ],
            'available' => true,
        ]);
    }
}

