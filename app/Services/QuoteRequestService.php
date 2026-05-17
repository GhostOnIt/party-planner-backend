<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CustomOffer;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestActivity;
use App\Models\QuoteRequestStage;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\CustomOfferCreatedNotification;
use App\Notifications\CustomOfferRespondedNotification;
use App\Notifications\NewQuoteRequestNotification;
use App\Notifications\QuoteRequestCallScheduledNotification;
use App\Notifications\QuoteRequestStaleNotification;
use App\Notifications\QuoteRequestUpdatedNotification;
use Illuminate\Support\Collection;

class QuoteRequestService
{
    public function __construct(protected SubscriptionService $subscriptionService) {}

    public function generateTrackingCode(): string
    {
        return 'QR-' . strtoupper(substr((string) str()->uuid(), 0, 8));
    }

    public function logActivity(
        QuoteRequest $quoteRequest,
        string $type,
        ?string $message = null,
        ?array $metadata = null,
        ?string $userId = null
    ): QuoteRequestActivity {
        return QuoteRequestActivity::create([
            'quote_request_id' => $quoteRequest->id,
            'user_id' => $userId,
            'activity_type' => $type,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    public function ensureWorkflowStages(): Collection
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

    public function notifyAdminsNewRequest(QuoteRequest $quoteRequest): void
    {
        $admins = User::where('role', UserRole::ADMIN->value)->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewQuoteRequestNotification($quoteRequest));
        }
    }

    public function notifyCustomerUpdate(QuoteRequest $quoteRequest, string $title, string $message): void
    {
        if (!$quoteRequest->user) {
            return;
        }

        $quoteRequest->user->notify(new QuoteRequestUpdatedNotification($quoteRequest, $title, $message));
    }

    public function notifyCustomerCallScheduled(QuoteRequest $quoteRequest): void
    {
        if (!$quoteRequest->user || !$quoteRequest->call_scheduled_at) {
            return;
        }

        $quoteRequest->user->notify(new QuoteRequestCallScheduledNotification($quoteRequest));
    }

    public function notifyCustomerOfferCreated(CustomOffer $offer): void
    {
        $quoteRequest = $offer->quoteRequest;
        if (!$quoteRequest?->user) {
            return;
        }

        $quoteRequest->user->notify(new CustomOfferCreatedNotification($offer));
    }

    public function notifyAdminsOfferResponded(CustomOffer $offer): void
    {
        $quoteRequest = $offer->quoteRequest;

        if ($quoteRequest?->assignedAdmin) {
            $quoteRequest->assignedAdmin->notify(new CustomOfferRespondedNotification($offer));
            return;
        }

        $admins = User::where('role', UserRole::ADMIN->value)->get();
        foreach ($admins as $admin) {
            $admin->notify(new CustomOfferRespondedNotification($offer));
        }
    }

    /**
     * Provisionne une souscription au plan Business à partir d'une offre acceptée.
     * Retourne null si la demande n'a pas d'utilisateur authentifié ou pas de plan associé.
     */
    public function createSubscriptionFromAcceptedOffer(CustomOffer $offer): ?Subscription
    {
        $quoteRequest = $offer->quoteRequest;

        if (!$quoteRequest || !$quoteRequest->user || !$quoteRequest->plan) {
            return null;
        }

        $subscription = $this->subscriptionService->createSubscriptionWithPlan(
            $quoteRequest->user,
            $quoteRequest->plan
        );

        // Override le tarif standard du plan avec le montant négocié de l'offre.
        $subscription->update([
            'base_price' => $offer->price_amount,
            'total_price' => $offer->price_amount,
        ]);

        $this->logActivity(
            $quoteRequest,
            'subscription_created',
            "Souscription provisionnée depuis l'offre \"{$offer->title}\" (en attente de paiement).",
            [
                'offer_id' => $offer->id,
                'subscription_id' => $subscription->id,
                'amount' => $offer->price_amount,
                'currency' => $offer->price_currency,
            ]
        );

        return $subscription->fresh();
    }

    public function notifyAdminsStaleRequest(QuoteRequest $quoteRequest, int $daysSinceLastActivity): void
    {
        if ($quoteRequest->assignedAdmin) {
            $quoteRequest->assignedAdmin->notify(
                new QuoteRequestStaleNotification($quoteRequest, $daysSinceLastActivity)
            );
            return;
        }

        $admins = User::where('role', UserRole::ADMIN->value)->get();
        foreach ($admins as $admin) {
            $admin->notify(new QuoteRequestStaleNotification($quoteRequest, $daysSinceLastActivity));
        }
    }
}
