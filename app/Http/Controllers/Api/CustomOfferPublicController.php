<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RespondToOfferRequest;
use App\Models\CustomOffer;
use App\Services\QuoteRequestService;
use Illuminate\Http\JsonResponse;

class CustomOfferPublicController extends Controller
{
    public function __construct(protected QuoteRequestService $quoteRequestService) {}

    public function show(string $clientToken): JsonResponse
    {
        $offer = CustomOffer::query()
            ->where('client_token', $clientToken)
            ->whereIn('status', ['sent', 'accepted', 'rejected', 'expired'])
            ->with('quoteRequest:id,tracking_code,company_name,contact_name')
            ->firstOrFail();

        // Check expiration
        if ($offer->isExpired()) {
            $offer->update(['status' => 'expired']);
            $offer->refresh();
        }

        return response()->json([
            'data' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'description' => $offer->description,
                'price_amount' => $offer->price_amount,
                'price_currency' => $offer->price_currency,
                'features' => $offer->features,
                'terms' => $offer->terms,
                'validity_days' => $offer->validity_days,
                'expires_at' => $offer->expires_at,
                'status' => $offer->status,
                'client_responded_at' => $offer->client_responded_at,
                'client_response_note' => $offer->client_response_note,
                'tracking_code' => $offer->quoteRequest?->tracking_code,
                'company_name' => $offer->quoteRequest?->company_name,
            ],
        ]);
    }

    public function respond(RespondToOfferRequest $request, string $clientToken): JsonResponse
    {
        $offer = CustomOffer::query()
            ->where('client_token', $clientToken)
            ->where('status', 'sent')
            ->firstOrFail();

        // Check expiration
        if ($offer->isExpired()) {
            $offer->update(['status' => 'expired']);
            return response()->json(['message' => 'Cette offre a expiré.'], 422);
        }

        $validated = $request->validated();
        $newStatus = $validated['action'] === 'accept' ? 'accepted' : 'rejected';

        $offer->update([
            'status' => $newStatus,
            'client_responded_at' => now(),
            'client_response_note' => $validated['response_note'] ?? null,
        ]);

        $statusLabel = $newStatus === 'accepted' ? 'acceptée' : 'refusée';

        $this->quoteRequestService->logActivity(
            $offer->quoteRequest,
            'offer_responded',
            "Offre \"{$offer->title}\" {$statusLabel} par le client.",
            [
                'offer_id' => $offer->id,
                'response' => $newStatus,
                'response_note' => $validated['response_note'] ?? null,
            ]
        );

        $this->quoteRequestService->notifyAdminsOfferResponded($offer);

        $subscription = null;
        if ($newStatus === 'accepted') {
            $subscription = $this->quoteRequestService->createSubscriptionFromAcceptedOffer($offer->fresh());
        }

        return response()->json([
            'message' => "Offre {$statusLabel} avec succès.",
            'data' => [
                'status' => $newStatus,
                'subscription_id' => $subscription?->id,
            ],
        ]);
    }
}
