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

        $stage = QuoteRequestStage::query()->orderBy('sort_order')->first();

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
