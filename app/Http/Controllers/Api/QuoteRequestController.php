<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequestRequest;
use App\Models\CustomOffer;
use App\Models\Plan;
use App\Models\QuoteRequest;
use App\Services\QuoteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteRequestController extends Controller
{
    public function __construct(protected QuoteRequestService $quoteRequestService) {}

    public function store(StoreQuoteRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $businessPlan = null;
        if (!empty($validated['plan_id'])) {
            $businessPlan = Plan::query()
                ->whereKey($validated['plan_id'])
                ->whereJsonContains('features->sales.contact_required', true)
                ->first();
        }

        $stages = $this->quoteRequestService->ensureWorkflowStages();
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
        $stages = $this->quoteRequestService->ensureWorkflowStages();
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
            ->with([
                'currentStage',
                'assignedAdmin:id,name',
                'activities' => function ($query) {
                    $query->latest()->limit(20);
                },
                'offers.creator:id,name',
            ])
            ->latest()
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function myOffers(Request $request): JsonResponse
    {
        $offers = CustomOffer::query()
            ->whereHas('quoteRequest', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['quoteRequest:id,tracking_code,company_name', 'creator:id,name'])
            ->latest()
            ->get();

        return response()->json(['data' => $offers]);
    }
}
