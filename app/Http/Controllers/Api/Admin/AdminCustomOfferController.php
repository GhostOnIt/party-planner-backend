<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomOfferRequest;
use App\Models\CustomOffer;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestStage;
use App\Services\QuoteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminCustomOfferController extends Controller
{
    public function __construct(protected QuoteRequestService $quoteRequestService) {}

    public function index(QuoteRequest $quoteRequest): JsonResponse
    {
        $offers = $quoteRequest->offers()
            ->with('creator:id,name')
            ->latest()
            ->get();

        return response()->json(['data' => $offers]);
    }

    public function store(StoreCustomOfferRequest $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validated();
        $validityDays = $validated['validity_days'] ?? 30;

        $offer = CustomOffer::create([
            ...$validated,
            'quote_request_id' => $quoteRequest->id,
            'created_by' => $request->user()->id,
            'price_currency' => $validated['price_currency'] ?? 'XAF',
            'validity_days' => $validityDays,
            'expires_at' => now()->addDays($validityDays),
            'status' => 'draft',
            'client_token' => Str::random(64),
        ]);

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'offer_created',
            "Offre \"{$offer->title}\" créée en brouillon.",
            ['offer_id' => $offer->id],
            $request->user()->id
        );

        return response()->json([
            'message' => 'Offre créée en brouillon.',
            'data' => $offer->load('creator:id,name'),
        ], 201);
    }

    public function update(StoreCustomOfferRequest $request, CustomOffer $customOffer): JsonResponse
    {
        if ($customOffer->status !== 'draft') {
            return response()->json(['message' => 'Seules les offres en brouillon peuvent être modifiées.'], 422);
        }

        $validated = $request->validated();

        if (isset($validated['validity_days'])) {
            $validated['expires_at'] = now()->addDays($validated['validity_days']);
        }

        $customOffer->update($validated);

        return response()->json([
            'message' => 'Offre mise à jour.',
            'data' => $customOffer->fresh()->load('creator:id,name'),
        ]);
    }

    public function send(CustomOffer $customOffer): JsonResponse
    {
        if ($customOffer->status !== 'draft') {
            return response()->json(['message' => 'Seules les offres en brouillon peuvent être envoyées.'], 422);
        }

        $customOffer->update([
            'status' => 'sent',
            'expires_at' => now()->addDays($customOffer->validity_days),
        ]);

        $quoteRequest = $customOffer->quoteRequest;

        // Auto-advance to custom_offer_created stage
        $offerStage = QuoteRequestStage::query()->where('slug', 'custom_offer_created')->first();
        if ($offerStage && $quoteRequest->current_stage_id !== $offerStage->id) {
            $quoteRequest->update([
                'current_stage_id' => $offerStage->id,
                'last_stage_changed_at' => now(),
            ]);
        }

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'offer_sent',
            "Offre \"{$customOffer->title}\" envoyée au client.",
            ['offer_id' => $customOffer->id, 'client_token' => $customOffer->client_token],
            auth()->id()
        );

        $this->quoteRequestService->notifyCustomerOfferCreated($customOffer);

        return response()->json([
            'message' => 'Offre envoyée au client.',
            'data' => $customOffer->fresh()->load('creator:id,name'),
        ]);
    }

    public function destroy(CustomOffer $customOffer): JsonResponse
    {
        if ($customOffer->status !== 'draft') {
            return response()->json(['message' => 'Seules les offres en brouillon peuvent être supprimées.'], 422);
        }

        $quoteRequest = $customOffer->quoteRequest;

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'offer_deleted',
            "Offre \"{$customOffer->title}\" supprimée.",
            ['offer_id' => $customOffer->id],
            auth()->id()
        );

        $customOffer->delete();

        return response()->json(['message' => 'Offre supprimée.']);
    }
}
