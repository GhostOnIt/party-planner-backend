<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\InitiatePaymentRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Display payment history.
     */
    public function index(Request $request): View
    {
        $payments = Payment::whereHas('subscription', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
            ->with('subscription.event')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $stats = $this->paymentService->getStatistics();

        return view('payments.index', compact('payments', 'stats'));
    }

    /**
     * Show payment initiation form.
     */
    public function initiate(Request $request, Subscription $subscription): View
    {
        if ($subscription->user_id !== $request->user()->id) {
            abort(403);
        }

        return view('payments.initiate', compact('subscription'));
    }

    /**
     * Initiate MTN Mobile Money payment.
     */
    public function initiateMtn(InitiatePaymentRequest $request): View|RedirectResponse
    {
        $subscription = Subscription::findOrFail($request->validated('subscription_id'));

        if ($subscription->user_id !== $request->user()->id) {
            abort(403);
        }

        $result = $this->paymentService->initiateMtnPayment(
            $subscription,
            $request->validated('phone')
        );

        if (!$result['success']) {
            return redirect()
                ->route('payments.initiate', $subscription)
                ->with('error', $result['message']);
        }

        return view('payments.processing', [
            'payment' => $result['payment'],
            'subscription' => $subscription,
            'reference' => $result['reference'],
        ]);
    }

    /**
     * Initiate Airtel Money payment.
     */
    public function initiateAirtel(InitiatePaymentRequest $request): View|RedirectResponse
    {
        $subscription = Subscription::findOrFail($request->validated('subscription_id'));

        if ($subscription->user_id !== $request->user()->id) {
            abort(403);
        }

        $result = $this->paymentService->initiateAirtelPayment(
            $subscription,
            $request->validated('phone')
        );

        if (!$result['success']) {
            return redirect()
                ->route('payments.initiate', $subscription)
                ->with('error', $result['message']);
        }

        return view('payments.processing', [
            'payment' => $result['payment'],
            'subscription' => $subscription,
            'reference' => $result['reference'],
        ]);
    }

    /**
     * Handle MTN callback.
     */
    public function callbackMtn(Request $request): JsonResponse
    {
        $this->paymentService->handleMtnCallback($request->all());

        return response()->json(['status' => 'received']);
    }

    /**
     * Handle Airtel callback.
     */
    public function callbackAirtel(Request $request): JsonResponse
    {
        $this->paymentService->handleAirtelCallback($request->all());

        return response()->json(['status' => 'received']);
    }

    /**
     * Check payment status.
     */
    public function status(Request $request, Payment $payment): View
    {
        if ($payment->subscription->user_id !== $request->user()->id) {
            abort(403);
        }

        $payment->load('subscription.event');
        $statusInfo = $this->paymentService->checkStatus($payment);

        return view('payments.status', compact('payment', 'statusInfo'));
    }

    /**
     * Poll payment status (returns partial view for AJAX updates).
     */
    public function poll(Request $request, Payment $payment): View
    {
        if ($payment->subscription->user_id !== $request->user()->id) {
            abort(403);
        }

        $payment = $payment->fresh();
        $statusInfo = $this->paymentService->checkStatus($payment);

        return view('payments.partials.status-poll', [
            'payment' => $payment,
            'statusInfo' => $statusInfo,
            'isCompleted' => $payment->isCompleted(),
            'isFailed' => $payment->isFailed(),
        ]);
    }

    /**
     * Show payment details.
     */
    public function show(Request $request, Payment $payment): View
    {
        if ($payment->subscription->user_id !== $request->user()->id) {
            abort(403);
        }

        $payment->load('subscription.event');

        return view('payments.show', compact('payment'));
    }

    /**
     * Retry failed payment.
     */
    public function retry(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->subscription->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$payment->isFailed()) {
            return redirect()
                ->route('payments.status', $payment)
                ->with('error', 'Ce paiement ne peut pas être réessayé.');
        }

        return redirect()
            ->route('payments.initiate', $payment->subscription)
            ->with('info', 'Veuillez réessayer le paiement.');
    }
}
