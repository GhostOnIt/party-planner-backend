<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PawaPayWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function handleDeposit(Request $request): JsonResponse
    {
        Log::info('pawaPay deposit webhook received', [
            'payload' => $request->all(),
            'headers' => $this->safeHeaders($request),
        ]);

        $this->paymentService->processPawaPayDepositCallback($request->all());

        return response()->json(['status' => 'success']);
    }

    public function handlePayout(Request $request): JsonResponse
    {
        Log::info('pawaPay payout webhook received', [
            'payload' => $request->all(),
            'headers' => $this->safeHeaders($request),
        ]);

        return response()->json(['status' => 'received']);
    }

    public function handleRefund(Request $request): JsonResponse
    {
        Log::info('pawaPay refund webhook received', [
            'payload' => $request->all(),
            'headers' => $this->safeHeaders($request),
        ]);

        return response()->json(['status' => 'received']);
    }

    private function safeHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->except(['authorization', 'cookie', 'x-xsrf-token'])
            ->all();
    }
}
