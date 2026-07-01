<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PawaPayService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Display payment history.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $payments = Payment::query()
            ->where(function ($outer) use ($userId) {
                $outer->whereHas('subscription', function ($query) use ($userId) {
                    $query->where(function ($q) use ($userId) {
                        $q->where('user_id', $userId)->whereNull('event_id');
                    })->orWhereHas('event', function ($eventQuery) use ($userId) {
                        $eventQuery->where('user_id', $userId);
                    });
                })->orWhereHas('subscription.event.collaborators', function ($c) use ($userId) {
                    $c->where('user_id', $userId)->whereNotNull('accepted_at');
                });
            })
            ->with([
                'subscription.event:id,title',
                'subscription.user:id,name,email',
                'subscription.plan:id,name,slug',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        $payments->getCollection()->transform(function (Payment $payment) {
            $payment->setAttribute('service_label', $this->buildServiceLabel($payment));
            return $payment;
        });

        return response()->json($payments);
    }

    protected function buildServiceLabel(Payment $payment): string
    {
        $subscription = $payment->subscription;
        if (!$subscription) {
            return 'Paiement';
        }

        $eventTitle = $subscription->event?->title;
        if ($eventTitle) {
            return 'Service événement ' . $eventTitle;
        }

        $planName = $subscription->plan?->name
            ?? ($subscription->plan_type ? strtoupper((string) $subscription->plan_type) : null);

        return $planName ? 'Abonnement ' . $planName : 'Abonnement';
    }

    /**
     * Download PDF receipt for a completed payment.
     */
    public function receipt(Request $request, Payment $payment): Response|JsonResponse
    {
        $this->authorize('viewReceipt', $payment);

        if (!$payment->isCompleted()) {
            return response()->json([
                'message' => 'Le reçu PDF est disponible une fois le paiement confirmé.',
            ], 422);
        }

        $payment->load(['subscription.user', 'subscription.event']);

        $pdf = Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'issuedAt' => now(),
        ]);

        $filename = 'recu-paiement-' . $payment->id . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Initiate a payment (auto-detect provider based on phone number).
     */
    public function initiate(Request $request): JsonResponse
    {
        $this->normalizeLegacyPaymentInput($request);

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'phone_number' => 'required|string',
            'amount' => 'sometimes|numeric|min:0',
            'plan' => 'sometimes|string|in:starter,pro',
            'plan_type' => 'sometimes|string|in:starter,pro', // Accept both
            'idempotency_key' => 'nullable|string|uuid',
        ]);

        $user = $request->user();
        $subscription = null;

        // Handle account-level subscription (no event_id)
        if (isset($validated['subscription_id'])) {
            $subscription = Subscription::findOrFail($validated['subscription_id']);
            
            // Verify ownership
            if ($subscription->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Vous n\'avez pas accès à cet abonnement.',
                ], 403);
            }

        }
        // Handle event-level subscription (with event_id)
        elseif (isset($validated['event_id'])) {
        $event = Event::findOrFail($validated['event_id']);

        // Check if user owns the event or is a collaborator
            if ($event->user_id !== $user->id) {
            $isCollaborator = $event->collaborators()
                    ->where('user_id', $user->id)
                ->whereNotNull('accepted_at')
                ->exists();

            if (!$isCollaborator) {
                return response()->json([
                    'message' => 'Vous n\'avez pas accès à cet événement.',
                ], 403);
            }
            }

            // Accept both 'plan' and 'plan_type' from frontend
            $planType = $validated['plan'] ?? $validated['plan_type'] ?? 'starter';

            // Get or create subscription with proper pricing
            $subscription = $event->subscription;
            if (!$subscription) {
                // Use expected_guests from event or default to 50
                $guestCount = $event->expected_guests ?? 50;
                $subscription = $this->subscriptionService->create($event, $event->user, $planType, $guestCount);
            }
        } else {
            return response()->json([
                'message' => 'Vous devez fournir soit event_id soit subscription_id.',
            ], 422);
        }

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        $idempotentResponse = $this->respondIfIdempotentPayment($subscription, $idempotencyKey);
        if ($idempotentResponse) {
            return $idempotentResponse;
        }

        // Detect provider from phone number
        $provider = $this->detectProvider($validated['phone_number']);
        $phone = $this->normalizePhone($validated['phone_number']);

        if (!$provider) {
            return response()->json([
                'message' => 'Numéro de téléphone non reconnu. Utilisez un numéro MTN ou Airtel.',
            ], 422);
        }

        // Initiate payment based on provider
        $result = $provider === 'mtn'
            ? $this->paymentService->initiateMtnPayment($subscription, $phone, $idempotencyKey)
            : $this->paymentService->initiateAirtelPayment($subscription, $phone, $idempotencyKey);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Erreur lors de l\'initiation du paiement.',
            ], 422);
        }

        return response()->json([
            'message' => 'Paiement initié. Veuillez confirmer sur votre téléphone.',
            'payment' => $result['payment'],
            'reference' => $result['reference'] ?? null,
            'provider' => $provider,
        ]);
    }

    /**
     * Initiate MTN Mobile Money payment.
     */
    public function initiateMtn(Request $request): JsonResponse
    {
        return $this->initiateMobileMoneyPayment(
            $request,
            'MTN',
            fn ($subscription, $phone, $key) => $this->paymentService->initiateMtnPayment($subscription, $phone, $key)
        );
    }

    /**
     * Initie un paiement Mobile Money (MTN ou Airtel) — pipeline commun :
     * validation → résolution subscription (event ou compte) → idempotence → appel provider.
     *
     * @param  callable(Subscription, string, ?string): array  $providerCall
     */
    private function initiateMobileMoneyPayment(Request $request, string $providerLabel, callable $providerCall): JsonResponse
    {
        $this->normalizeLegacyPaymentInput($request);

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'phone_number' => 'required|string',
            'plan' => 'sometimes|string|in:starter,pro',
            'plan_type' => 'sometimes|string|in:starter,pro',
            'amount' => 'sometimes|numeric|min:0',
            'idempotency_key' => 'nullable|string|uuid',
        ]);

        $user = $request->user();
        $subscription = null;

        if (isset($validated['subscription_id'])) {
            $subscription = Subscription::findOrFail($validated['subscription_id']);

            if ($subscription->user_id !== $user->id) {
                return response()->json(['message' => "Vous n'avez pas accès à cet abonnement."], 403);
            }

        } elseif (isset($validated['event_id'])) {
            $event = Event::findOrFail($validated['event_id']);

            if ($event->user_id !== $user->id) {
                return response()->json(['message' => "Vous n'avez pas accès à cet événement."], 403);
            }

            $planType = $validated['plan'] ?? $validated['plan_type'] ?? 'starter';

            $subscription = $event->subscription;
            if (!$subscription) {
                $guestCount = $event->expected_guests ?? 50;
                $subscription = $this->subscriptionService->create($event, $event->user, $planType, $guestCount);
            }
        } else {
            return response()->json(['message' => 'Vous devez fournir soit event_id soit subscription_id.'], 422);
        }

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        $idempotentResponse = $this->respondIfIdempotentPayment($subscription, $idempotencyKey);
        if ($idempotentResponse) {
            return $idempotentResponse;
        }

        $phone = $this->normalizePhone($validated['phone_number']);
        $result = $providerCall($subscription, $phone, $idempotencyKey);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? "Erreur lors de l'initiation du paiement {$providerLabel}.",
            ], 422);
        }

        return response()->json([
            'message' => "Paiement {$providerLabel} initié. Veuillez confirmer sur votre téléphone.",
            'payment' => $result['payment'],
            'reference' => $result['reference'] ?? null,
        ]);
    }

    /**
     * Initiate Airtel Money payment.
     */
    public function initiateAirtel(Request $request): JsonResponse
    {
        return $this->initiateMobileMoneyPayment(
            $request,
            'Airtel',
            fn ($subscription, $phone, $key) => $this->paymentService->initiateAirtelPayment($subscription, $phone, $key)
        );
    }

    /**
     * Initiate a pawaPay deposit.
     */
    public function initiatePawaPay(Request $request): JsonResponse
    {
        $this->normalizeLegacyPaymentInput($request);

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'phone_number' => 'required|string',
            'provider' => 'nullable|string',
            'country' => 'nullable|string|size:3',
            'currency' => 'nullable|string|size:3',
            'plan' => 'sometimes|string|in:starter,pro',
            'plan_type' => 'sometimes|string|in:starter,pro',
            'idempotency_key' => 'nullable|string|uuid',
        ]);

        $subscription = $this->resolveSubscriptionForPayment($request, $validated);
        if ($subscription instanceof JsonResponse) {
            return $subscription;
        }

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        $idempotentResponse = $this->respondIfIdempotentPayment($subscription, $idempotencyKey);
        if ($idempotentResponse) {
            return $idempotentResponse;
        }

        $result = $this->paymentService->initiatePawaPayDeposit(
            $subscription,
            $validated['phone_number'],
            $validated['provider'] ?? null,
            $validated['country'] ?? null,
            $validated['currency'] ?? null,
            $idempotencyKey
        );

        if (!($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Erreur lors de l\'initiation du paiement pawaPay.',
                'payment' => $result['payment'] ?? null,
                'provider_error' => $result['body'] ?? null,
            ], 422);
        }

        return response()->json([
            'message' => 'Paiement pawaPay initié.',
            'payment' => $result['payment'],
            'reference' => $result['reference'] ?? null,
            'provider' => 'pawapay',
            'provider_response' => $result['provider_response'] ?? null,
        ]);
    }

    /**
     * Public market/payment configuration for frontend checkout selection.
     */
    public function markets(PawaPayService $pawaPayService): JsonResponse
    {
        $config = $pawaPayService->publicMarketConfiguration();
        $activeConfiguration = null;

        if ($config['enabled'] && config('partyplanner.payments.pawapay.api_token')) {
            $activeConfiguration = $pawaPayService->activeConfiguration();
        }

        return response()->json([
            'pawapay' => $config,
            'active_configuration' => $activeConfiguration,
        ]);
    }

    /**
     * Check payment status.
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        // Verify ownership
        if (!$payment->canBeAccessedBy($request->user())) {
            return response()->json([
                'message' => 'Paiement non trouvé.',
            ], 404);
        }

        $payment->load('subscription.event');
        $statusInfo = $this->paymentService->checkStatus($payment);

        return response()->json([
            'payment' => $payment,
            'status_info' => $statusInfo,
        ]);
    }

    /**
     * Poll payment status.
     */
    public function poll(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->canBeAccessedBy($request->user())) {
            return response()->json([
                'message' => 'Paiement non trouvé.',
            ], 404);
        }

        $payment = $payment->fresh();
        $statusInfo = $this->paymentService->checkStatus($payment);

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
            ],
            'status_info' => $statusInfo,
            'is_completed' => $payment->isCompleted(),
            'is_failed' => $payment->isFailed(),
            'is_pending' => $payment->isPending(),
        ]);
    }

    /**
     * Retry failed payment.
     */
    public function retry(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->canBeAccessedBy($request->user())) {
            return response()->json([
                'message' => 'Paiement non trouvé.',
            ], 404);
        }

        if (!$payment->isFailed()) {
            return response()->json([
                'message' => 'Ce paiement ne peut pas être réessayé.',
            ], 422);
        }

        $this->normalizeLegacyPaymentInput($request);

        $validated = $request->validate([
            'phone_number' => 'nullable|string',
        ]);

        $subscription = $payment->subscription;
        $phoneNumber = $validated['phone_number']
            ?? $payment->metadata['phone_number']
            ?? $payment->metadata['pawapay']['phone_number']
            ?? null;

        if (!$phoneNumber) {
            return response()->json([
                'message' => 'Un numéro de téléphone est requis pour relancer ce paiement.',
            ], 422);
        }

        $phone = $this->normalizePhone($phoneNumber);

        $result = match ($payment->payment_method) {
            'mtn_mobile_money' => $this->paymentService->initiateMtnPayment($subscription, $phone, null),
            'pawapay' => $this->paymentService->initiatePawaPayDeposit(
                $subscription,
                $phone,
                $payment->metadata['provider'] ?? $payment->metadata['pawapay']['provider'] ?? null,
                $payment->metadata['country'] ?? $payment->metadata['pawapay']['country'] ?? null,
                $payment->currency,
                null
            ),
            default => $this->paymentService->initiateAirtelPayment($subscription, $phone, null),
        };

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Erreur lors de la tentative de paiement.',
            ], 422);
        }

        return response()->json([
            'message' => 'Nouveau paiement initié. Veuillez confirmer sur votre téléphone.',
            'payment' => $result['payment'],
            'reference' => $result['reference'] ?? null,
        ]);
    }

    /**
     * Return existing payment response when idempotency_key matches a pending or completed payment.
     */
    protected function respondIfIdempotentPayment(Subscription $subscription, ?string $idempotencyKey): ?JsonResponse
    {
        if (!$idempotencyKey) {
            return null;
        }

        $existing = Payment::where('subscription_id', $subscription->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (!$existing) {
            return null;
        }

        if ($existing->isFailed() || $existing->isRefunded()) {
            $existing->update(['idempotency_key' => null]);

            return null;
        }

        $provider = match ($existing->payment_method) {
            'mtn_mobile_money' => 'mtn',
            'airtel_money' => 'airtel',
            'pawapay' => 'pawapay',
            default => $existing->payment_method,
        };

        return response()->json([
            'message' => 'Paiement existant (idempotent).',
            'payment' => $existing->load('subscription.event:id,title'),
            'reference' => $existing->transaction_reference,
            'provider' => $provider,
            'idempotent' => true,
        ]);
    }

    /**
     * Detect payment provider from phone number.
     * Formats acceptés: +2420XXXXXXXX ou 0XXXXXXXX
     */
    protected function detectProvider(string $phone): ?string
    {
        // Remove spaces, dashes and other separators
        $phone = preg_replace('/[\s\-\.]/', '', $phone);

        if (str_starts_with($phone, '00242')) {
            $phone = substr($phone, 5);
        }

        // Remove country code +242 or 242 if present
        $phone = preg_replace('/^(\+?242)/', '', $phone);

        // Remove leading 0 if present
        $phone = ltrim($phone, '0');

        // MTN Congo prefix: 06 (after removing leading 0: 6)
        if (preg_match('/^6/', $phone)) {
            return 'mtn';
        }

        // Airtel Congo prefixes: 04, 05 (after removing leading 0: 4, 5)
        if (preg_match('/^[45]/', $phone)) {
            return 'airtel';
        }

        return null;
    }

    private function normalizeLegacyPaymentInput(Request $request): void
    {
        if (!$request->filled('phone_number') && $request->filled('phone')) {
            $request->merge(['phone_number' => $request->input('phone')]);
        }
    }

    /**
     * Normalize phone number to standard format.
     * Format Congo: +242OXXXXXXXX (avec le 0 du préfixe local)
     * In sandbox mode, test numbers (starting with 467) are returned as-is.
     */
    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // In sandbox mode, return test numbers as-is (they start with 467)
        $mtnConfig = config('partyplanner.payments.mtn_mobile_money');
        if ($mtnConfig['environment'] === 'sandbox' && str_starts_with($phone, '467')) {
            return $phone;
        }

        if (str_starts_with($phone, '00242')) {
            return '+242' . substr($phone, 5);
        }

        // If starts with +242, keep as is
        if (str_starts_with($phone, '+242')) {
            return $phone;
        }

        // If starts with 242, add +
        if (str_starts_with($phone, '242')) {
            return '+' . $phone;
        }

        // If starts with 0 (local format), add +242 (keep the 0)
        if (str_starts_with($phone, '0')) {
            return '+242' . $phone;
        }

        // Otherwise, add +2420 prefix (assuming local number without leading 0)
        return '+2420' . $phone;
    }

    private function resolveSubscriptionForPayment(Request $request, array $validated): Subscription|JsonResponse
    {
        $user = $request->user();

        if (isset($validated['subscription_id'])) {
            $subscription = Subscription::findOrFail($validated['subscription_id']);

            if ($subscription->user_id !== $user->id) {
                return response()->json(['message' => "Vous n'avez pas accès à cet abonnement."], 403);
            }

            return $subscription;
        }

        if (!isset($validated['event_id'])) {
            return response()->json(['message' => 'Vous devez fournir soit event_id soit subscription_id.'], 422);
        }

        $event = Event::findOrFail($validated['event_id']);

        if ($event->user_id !== $user->id) {
            $isCollaborator = $event->collaborators()
                ->where('user_id', $user->id)
                ->whereNotNull('accepted_at')
                ->exists();

            if (!$isCollaborator) {
                return response()->json(['message' => "Vous n'avez pas accès à cet événement."], 403);
            }
        }

        $planType = $validated['plan'] ?? $validated['plan_type'] ?? 'starter';
        $subscription = $event->subscription;

        if (!$subscription) {
            $guestCount = $event->expected_guests ?? 50;
            $subscription = $this->subscriptionService->create($event, $event->user, $planType, $guestCount);
        }

        return $subscription;
    }

}
