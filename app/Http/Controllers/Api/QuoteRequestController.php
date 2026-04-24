<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestStage;
use App\Services\QuoteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteRequestController extends Controller
{
    public function __construct(protected QuoteRequestService $quoteRequestService) {}

    private function ensureWorkflowStages(): \Illuminate\Support\Collection
    {
        $workflow = collect([
            ['name' => 'En attente de traitement', 'slug' => 'pending_processing', 'sort_order' => 0],
            ['name' => 'Assignée à un admin', 'slug' => 'assigned_admin', 'sort_order' => 1],
            ['name' => 'Call programmé', 'slug' => 'call_scheduled', 'sort_order' => 2],
            ['name' => 'Offre personnalisée créée', 'slug' => 'custom_offer_created', 'sort_order' => 3],
            ['name' => 'Clôturée', 'slug' => 'closed', 'sort_order' => 4],
        ]);

        QuoteRequestStage::query()
            ->where('is_system', true)
            ->whereNotIn('slug', $workflow->pluck('slug'))
            ->update(['is_active' => false]);

        foreach ($workflow as $stage) {
            QuoteRequestStage::updateOrCreate(
                ['slug' => $stage['slug']],
                [
                    'name' => $stage['name'],
                    'sort_order' => $stage['sort_order'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }

        return QuoteRequestStage::query()
            ->where('is_active', true)
            ->whereIn('slug', $workflow->pluck('slug'))
            ->orderBy('sort_order')
            ->get();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'exists:plans,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'business_needs' => ['required', 'string', 'min:20', 'max:3000'],
            'budget_estimate' => ['nullable', 'integer', 'min:0'],
            'team_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'timeline' => ['nullable', 'string', 'max:255'],
            'event_types' => ['nullable', 'array', 'max:20'],
            'event_types.*' => ['string', 'max:100'],
        ]);

        $businessPlan = null;
        if (!empty($validated['plan_id'])) {
            $businessPlan = Plan::query()
                ->whereKey($validated['plan_id'])
                ->whereJsonContains('features->sales.contact_required', true)
                ->first();
        }

        $stages = $this->ensureWorkflowStages();
        $stage = $stages->firstWhere('slug', 'pending_processing') ?? $stages->first();

        $quoteRequest = QuoteRequest::create([
            ...$validated,
            'tracking_code' => $this->quoteRequestService->generateTrackingCode(),
            'user_id' => $request->user()?->id,
            'plan_id' => $businessPlan?->id,
            'status' => 'open',
            'current_stage_id' => $stage?->id,
            'last_stage_changed_at' => now(),
        ]);

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'created',
            'Nouvelle demande de devis soumise.',
            ['stage_slug' => $stage?->slug],
            $request->user()?->id
        );
        $this->quoteRequestService->notifyAdminsNewRequest($quoteRequest);

        return response()->json([
            'message' => 'Demande de devis envoyée avec succès.',
            'data' => $quoteRequest->load(['currentStage', 'plan']),
        ], 201);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $stages = $this->ensureWorkflowStages();
        $firstStage = $stages->firstWhere('slug', 'pending_processing') ?? $stages->first();
        if ($firstStage) {
            QuoteRequest::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('current_stage_id')
                ->update([
                    'current_stage_id' => $firstStage->id,
                    'last_stage_changed_at' => now(),
                ]);
        }

        $requests = QuoteRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['currentStage', 'assignedAdmin:id,name', 'activities' => function ($query) {
                $query->latest()->limit(20);
            }])
            ->latest()
            ->get();

        return response()->json(['data' => $requests]);
    }
}
