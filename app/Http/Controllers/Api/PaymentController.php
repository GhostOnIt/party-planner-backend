<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $payments = Payment::whereHas('subscription.event', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
            ->with(['subscription.event:id,title'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($payments);
    }

    /**
     * Initiate a payment (auto-detect provider based on phone number).
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'phone_number' => 'required|string',
            'amount' => 'sometimes|numeric|min:0',
            'plan' => 'sometimes|string|in:starter,pro',
            'plan_type' => 'sometimes|string|in:starter,pro', // Accept both
        ]);

        $event = Event::findOrFail($validated['event_id']);

        // Check if user owns the event or is a collaborator
        if ($event->user_id !== $request->user()->id) {
            $isCollaborator = $event->collaborators()
                ->where('user_id', $request->user()->id)
                ->whereNotNull('accepted_at')
                ->exists();

            if (!$isCollaborator) {
                return response()->json([
                    'message' => 'Vous n\'avez pas accès à cet événement.',
                ], 403);
            }
        }

        // Detect provider from phone number
        $provider = $this->detectProvider($validated['phone_number']);
        $phone = $this->normalizePhone($validated['phone_number']);

        if (!$provider) {
            return response()->json([
                'message' => 'Numéro de téléphone non reconnu. Utilisez un numéro MTN ou Airtel.',
            ], 422);
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

        // Initiate payment based on provider
        $result = $provider === 'mtn'
            ? $this->paymentService->initiateMtnPayment($subscription, $phone)
            : $this->paymentService->initiateAirtelPayment($subscription, $phone);

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
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'phone_number' => 'required|string',
            'plan' => 'sometimes|string|in:starter,pro',
            'plan_type' => 'sometimes|string|in:starter,pro', // Accept both
        ]);

        $event = Event::findOrFail($validated['event_id']);

        if ($event->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'avez pas accès à cet événement.',
            ], 403);
        }

        // Accept both 'plan' and 'plan_type' from frontend
        $planType = $validated['plan'] ?? $validated['plan_type'] ?? 'starter';

        $subscription = $event->subscription;
        if (!$subscription) {
            $guestCount = $event->expected_guests ?? 50;
            $subscription = $this->subscriptionService->create($event, $event->user, $planType, $guestCount);
        }

        $phone = $this->normalizePhone($validated['phone_number']);
        $result = $this->paymentService->initiateMtnPayment($subscription, $phone);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Erreur lors de l\'initiation du paiement MTN.',
            ], 422);
        }

        return response()->json([
            'message' => 'Paiement MTN initié. Veuillez confirmer sur votre téléphone.',
            'payment' => $result['payment'],
            'reference' => $result['reference'] ?? null,
        ]);
    }

    /**
     * Initiate Airtel Money payment.
     */
    public function initiateAirtel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'phone_number' => 'required|string',
            'plan' => 'sometimes|string|in:starter,pro',
            'plan_type' => 'sometimes|string|in:starter,pro', // Accept both
        ]);

        $event = Event::findOrFail($validated['event_id']);

        if ($event->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'avez pas accès à cet événement.',
            ], 403);
        }

        // Accept both 'plan' and 'plan_type' from frontend
        $planType = $validated['plan'] ?? $validated['plan_type'] ?? 'starter';

        $subscription = $event->subscription;
        if (!$subscription) {
            $guestCount = $event->expected_guests ?? 50;
            $subscription = $this->subscriptionService->create($event, $event->user, $planType, $guestCount);
        }

        $phone = $this->normalizePhone($validated['phone_number']);
        $result = $this->paymentService->initiateAirtelPayment($subscription, $phone);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Erreur lors de l\'initiation du paiement Airtel.',
            ], 422);
        }

        return response()->json([
            'message' => 'Paiement Airtel initié. Veuillez confirmer sur votre téléphone.',
            'payment' => $result['payment'],
            'reference' => $result['reference'] ?? null,
        ]);
    }

    /**
     * Check payment status.
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        // Verify ownership
        if (!$this->userCanAccessPayment($request->user(), $payment)) {
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
        if (!$this->userCanAccessPayment($request->user(), $payment)) {
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
        if (!$this->userCanAccessPayment($request->user(), $payment)) {
            return response()->json([
                'message' => 'Paiement non trouvé.',
            ], 404);
        }

        if (!$payment->isFailed()) {
            return response()->json([
                'message' => 'Ce paiement ne peut pas être réessayé.',
            ], 422);
        }

        $validated = $request->validate([
            'phone_number' => 'required|string',
        ]);

        $subscription = $payment->subscription;
        $phone = $this->normalizePhone($validated['phone_number']);

        $result = $payment->payment_method === 'mtn_mobile_money'
            ? $this->paymentService->initiateMtnPayment($subscription, $phone)
            : $this->paymentService->initiateAirtelPayment($subscription, $phone);

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
     * Detect payment provider from phone number.
     * Formats acceptés: +2420XXXXXXXX ou 0XXXXXXXX
     */
    protected function detectProvider(string $phone): ?string
    {
        // Remove spaces, dashes and other separators
        $phone = preg_replace('/[\s\-\.]/', '', $phone);

        // Remove country code +242 if present
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

    /**
     * Check if user can access payment.
     */
    protected function userCanAccessPayment($user, Payment $payment): bool
    {
        $subscription = $payment->subscription;
        if (!$subscription) {
            return false;
        }

        $event = $subscription->event;
        if (!$event) {
            return false;
        }

        // Owner can access
        if ($event->user_id === $user->id) {
            return true;
        }

        // Accepted collaborator can access
        return $event->collaborators()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->exists();
    }
}
