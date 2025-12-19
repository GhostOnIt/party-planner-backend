<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileMoneyWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Handle MTN Mobile Money webhook.
     */
    public function handleMtn(Request $request): JsonResponse
    {
        Log::info('MTN webhook received', ['data' => $request->all()]);

        // Validate webhook signature if configured
        if (!$this->validateMtnSignature($request)) {
            Log::warning('MTN webhook: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();

        try {
            $this->paymentService->handleMtnCallback($data);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('MTN webhook processing error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle Airtel Money webhook.
     */
    public function handleAirtel(Request $request): JsonResponse
    {
        Log::info('Airtel webhook received', ['data' => $request->all()]);

        // Validate webhook signature if configured
        if (!$this->validateAirtelSignature($request)) {
            Log::warning('Airtel webhook: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();

        try {
            $this->paymentService->handleAirtelCallback($data);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Airtel webhook processing error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Validate MTN webhook signature.
     */
    protected function validateMtnSignature(Request $request): bool
    {
        $secret = config('partyplanner.payments.mtn_mobile_money.webhook_secret');

        // If no secret is configured, skip validation (development mode)
        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-Callback-Signature');
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate Airtel webhook signature.
     */
    protected function validateAirtelSignature(Request $request): bool
    {
        $secret = config('partyplanner.payments.airtel_money.webhook_secret');

        // If no secret is configured, skip validation (development mode)
        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-Airtel-Signature');
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
