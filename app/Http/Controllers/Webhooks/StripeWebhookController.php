<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Handle Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            Log::warning('Stripe webhook: Missing signature');
            return response()->json(['error' => 'Missing signature'], 400);
        }

        $result = $this->stripeService->handleWebhook($payload, $signature);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 400);
        }

        return response()->json(['status' => 'success', 'message' => $result['message']]);
    }
}
