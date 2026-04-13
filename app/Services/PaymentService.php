<?php

namespace App\Services;

use App\Jobs\ProcessPaymentCallbackJob;
use App\Jobs\SendPaymentConfirmationJob;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lepresk\MomoApi\MomoApi;

class PaymentService
{
    /**
     * Initiate MTN Mobile Money payment.
     */
    public function initiateMtnPayment(Subscription $subscription, string $phoneNumber, ?string $idempotencyKey = null): array
    {
        $payment = $this->createPayment($subscription, 'mtn_mobile_money', $idempotencyKey);

        // Validate phone number format for MTN (starts with 06 in Congo)
        if (!$this->isValidMtnNumber($phoneNumber)) {
            $payment->markInitiationFailed();

            return [
                'success' => false,
                'message' => 'Numéro MTN invalide. Le numéro doit commencer par 06.',
                'payment' => $payment->fresh(),
            ];
        }

        $config = config('partyplanner.payments.mtn_mobile_money');

        if (!$config['enabled']) {
            $payment->markInitiationFailed();

            return [
                'success' => false,
                'message' => 'MTN Mobile Money n\'est pas disponible actuellement.',
                'payment' => $payment->fresh(),
            ];
        }

        // Simulation mode for local development (set MTN_SIMULATE=true in .env)
        if ($config['simulate'] ?? false) {
            return $this->simulateMtnPayment($payment, $phoneNumber);
        }

        try {
            $baseUrl  = MomoApi::getBaseUrl($config['environment']);
            $timeout  = (int) ($config['http']['timeout'] ?? 30);
            $verifySsl = $config['http']['verify_ssl'] ?? true;

            // Step 1 — Get Bearer token
            /** @var \Illuminate\Http\Client\Response $tokenResponse */
            $tokenResponse = Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->when(!$verifySsl && !app()->isProduction(), fn ($h) => $h->withoutVerifying())
                ->withBasicAuth($config['api_user'], $config['api_key'])
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $config['subscription_key']])
                ->post('/collection/token/');

            if (!$tokenResponse->successful()) {
                Log::error('MTN token request failed', [
                    'subscription_id' => $subscription->id,
                    'status'          => $tokenResponse->status(),
                    'body'            => $tokenResponse->body(),
                ]);
                $payment->markInitiationFailed();
                return [
                    'success' => false,
                    'message' => 'Authentification MTN échouée (code ' . $tokenResponse->status() . ').',
                    'payment' => $payment->fresh(),
                ];
            }

            $accessToken = $tokenResponse->json('access_token');

            // Step 2 — Build payment params
            $formattedPhone = $this->formatPhoneNumber($phoneNumber, true);
            $currency = $config['environment'] === 'sandbox'
                ? 'EUR'
                : ($config['currency'] ?? config('partyplanner.currency.code', 'XAF'));

            // TODO TEST — montant fixé à 2 XAF pour tests prod. Retirer avant vrai déploiement.
            $amount     = '2';
            $externalId = Str::uuid()->toString();

            $description = 'Paiement Party Planner';
            if ($subscription->event) {
                $description .= ' - ' . $subscription->event->title;
            } else {
                $planName    = $subscription->plan?->name ?? $subscription->plan_type ?? 'Plan';
                $description .= " - Abonnement {$planName}";
            }

            // Step 3 — RequestToPay
            // Use asJson() so Content-Type is set once (avoid duplicate headers vs manual Content-Type + WAF/proxy 500s).
            $headers = [
                'Ocp-Apim-Subscription-Key' => $config['subscription_key'],
                'X-Reference-Id'            => $externalId,
                'X-Target-Environment'      => $config['environment'],
                'Authorization'             => 'Bearer ' . $accessToken,
            ];

            if (!empty($config['callback_url'])) {
                $headers['X-Callback-Url'] = $config['callback_url'];
            }

            // MTN: keep payerMessage readable; strip only risky chars / cap length (empty message can cause API errors).
            $payerMessage = trim(preg_replace('/[^\p{L}\p{N}\s\-.,]/u', '', $description) ?? '');
            if ($payerMessage === '') {
                $payerMessage = 'Party Planner payment';
            }
            $payerMessage = Str::substr($payerMessage, 0, 140);

            $payeeNote = 'Subscription ' . $subscription->id;

            $requestToPayPayload = [
                'amount'       => $amount,
                'currency'     => $currency,
                'externalId'   => $externalId,
                'payer'        => ['partyIdType' => 'MSISDN', 'partyId' => $formattedPhone],
                'payerMessage' => $payerMessage,
                'payeeNote'    => $payeeNote,
            ];

            Log::info('MTN requesttopay outgoing request', [
                'subscription_id' => $subscription->id,
                'payment_id'      => $payment->id,
                'environment'     => $config['environment'],
                'endpoint'        => $baseUrl . '/collection/v1_0/requesttopay',
                'payload'         => $requestToPayPayload,
            ]);

            /** @var \Illuminate\Http\Client\Response $payResponse */
            $payResponse = Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->when(!$verifySsl && !app()->isProduction(), fn ($h) => $h->withoutVerifying())
                ->asJson()
                ->withHeaders($headers)
                ->post('/collection/v1_0/requesttopay', $requestToPayPayload);

            Log::info('MTN requesttopay response', [
                'subscription_id' => $subscription->id,
                'status'          => $payResponse->status(),
                'body'            => $payResponse->body(),
                'x_reference_id'  => $externalId,
                'phone'           => $formattedPhone,
                'amount'          => $amount,
                'currency'        => $currency,
                'environment'     => $config['environment'],
            ]);

            if ($payResponse->status() === 202) {
                $payment->update([
                    'transaction_reference' => $externalId,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'phone_number'    => $phoneNumber,
                        'external_id'     => $externalId,
                        'momo_payment_id' => $externalId,
                        'initiated_at'    => now()->toIso8601String(),
                    ]),
                ]);

                return [
                    'success'   => true,
                    'message'   => 'Paiement initié. Veuillez confirmer sur votre téléphone.',
                    'payment'   => $payment->fresh(),
                    'reference' => $externalId,
                ];
            }

            // Non-202 — WAF/firewall HTML vs real MTN JSON error
            $rawBody    = $payResponse->body();
            $isWafBlock = str_contains($rawBody, 'Request Rejected') || str_starts_with(ltrim($rawBody), '<');

            if ($isWafBlock) {
                Log::error('MTN requesttopay blocked by WAF/firewall', [
                    'subscription_id' => $subscription->id,
                    'status'          => $payResponse->status(),
                    'raw_body'        => $rawBody,
                    'callback_url'    => $config['callback_url'] ?? null,
                ]);

                $payment->markInitiationFailed();

                return [
                    'success' => false,
                    'message' => 'Service MTN temporairement indisponible. Veuillez réessayer dans quelques minutes.',
                    'payment' => $payment->fresh(),
                ];
            }

            $errorJson = $payResponse->json() ?? [];
            Log::error('MTN requesttopay rejected', [
                'subscription_id' => $subscription->id,
                'status'          => $payResponse->status(),
                'raw_body'        => $rawBody,
                'error_code'      => $errorJson['code'] ?? null,
                'error_message'   => $errorJson['message'] ?? null,
            ]);

            $payment->markAsFailed();

            $userMessage = $payResponse->status() >= 500
                ? 'Erreur côté MTN. Réessayez plus tard ou contactez le support.'
                : 'Paiement MTN refusé (code ' . $payResponse->status() . '). Vérifiez le numéro et réessayez.';

            return [
                'success' => false,
                'message' => $userMessage,
                'payment' => $payment,
            ];

        } catch (\Exception $e) {
            Log::error('MTN payment exception', [
                'subscription_id' => $subscription->id,
                'error_class'     => get_class($e),
                'error'           => $e->getMessage(),
                'mtn_environment' => $config['environment'],
                'mtn_base_url'    => MomoApi::getBaseUrl($config['environment']),
            ]);

            $payment->markAsFailed();

            return [
                'success' => false,
                'message' => 'Erreur de connexion. Veuillez réessayer.',
                'payment' => $payment,
            ];
        }
    }

    /**
     * Initiate Airtel Money payment.
     */
    public function initiateAirtelPayment(Subscription $subscription, string $phoneNumber, ?string $idempotencyKey = null): array
    {
        $payment = $this->createPayment($subscription, 'airtel_money', $idempotencyKey);

        // Validate phone number format for Airtel (starts with 04 or 05 in Congo)
        if (!$this->isValidAirtelNumber($phoneNumber)) {
            $payment->markInitiationFailed();

            return [
                'success' => false,
                'message' => 'Numéro Airtel invalide. Le numéro doit commencer par 04 ou 05.',
                'payment' => $payment->fresh(),
            ];
        }

        $config = config('partyplanner.payments.airtel_money');

        if (!$config['enabled']) {
            $payment->markInitiationFailed();

            return [
                'success' => false,
                'message' => 'Airtel Money n\'est pas disponible actuellement.',
                'payment' => $payment->fresh(),
            ];
        }

        try {
            $externalId = 'PP-' . Str::upper(Str::random(12));

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAirtelAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($config['api_url'] . '/merchant/v1/payments/', [
                'reference' => $externalId,
                'subscriber' => [
                    'country' => 'CM',
                    'currency' => config('partyplanner.currency.code', 'XAF'),
                    'msisdn' => $this->formatPhoneNumber($phoneNumber),
                ],
                'transaction' => [
                    'amount' => $subscription->total_price,
                    'country' => 'CM',
                    'currency' => config('partyplanner.currency.code', 'XAF'),
                    'id' => $externalId,
                ],
            ]);

            if ($response->successful()) {
                $payment->update([
                    'transaction_reference' => $externalId,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'phone_number' => $phoneNumber,
                        'external_id' => $externalId,
                        'initiated_at' => now()->toIso8601String(),
                    ]),
                ]);

                return [
                    'success' => true,
                    'message' => 'Paiement initié. Veuillez confirmer sur votre téléphone.',
                    'payment' => $payment->fresh(),
                    'reference' => $externalId,
                ];
            }

            Log::error('Airtel payment initiation failed', [
                'subscription_id' => $subscription->id,
                'response' => $response->body(),
            ]);

            $payment->markAsFailed();

            return [
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement. Veuillez réessayer.',
                'payment' => $payment,
            ];

        } catch (\Exception $e) {
            Log::error('Airtel payment exception', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            $payment->markAsFailed();

            return [
                'success' => false,
                'message' => 'Erreur de connexion. Veuillez réessayer.',
                'payment' => $payment,
            ];
        }
    }

    /**
     * Handle MTN callback.
     */
    public function handleMtnCallback(array $data): bool
    {
        ProcessPaymentCallbackJob::dispatch('mtn', $data);

        return true;
    }

    /**
     * Handle Airtel callback.
     */
    public function handleAirtelCallback(array $data): bool
    {
        ProcessPaymentCallbackJob::dispatch('airtel', $data);

        return true;
    }

    /**
     * Process MTN callback synchronously.
     */
    public function processMtnCallback(array $data): void
    {
        $externalId = $data['externalId'] ?? $data['referenceId'] ?? null;

        if (!$externalId) {
            Log::warning('MTN callback missing external ID', $data);
            return;
        }

        $payment = Payment::where('transaction_reference', $externalId)->first();

        if (!$payment) {
            Log::warning('MTN callback: payment not found', ['external_id' => $externalId]);
            return;
        }

        $status = strtoupper($data['status'] ?? '');

        if ($status === 'SUCCESSFUL') {
            $payment->markAsCompleted($data['financialTransactionId'] ?? null);
            SendPaymentConfirmationJob::dispatch($payment);
            Log::info('MTN payment completed', ['payment_id' => $payment->id]);
        } elseif (in_array($status, ['FAILED', 'REJECTED', 'TIMEOUT'])) {
            $payment->markAsFailed();
            Log::info('MTN payment failed', ['payment_id' => $payment->id, 'status' => $status]);
        }
    }

    /**
     * Process Airtel callback synchronously.
     */
    public function processAirtelCallback(array $data): void
    {
        $reference = $data['transaction']['id'] ?? null;

        if (!$reference) {
            Log::warning('Airtel callback missing reference', $data);
            return;
        }

        $payment = Payment::where('transaction_reference', $reference)->first();

        if (!$payment) {
            Log::warning('Airtel callback: payment not found', ['reference' => $reference]);
            return;
        }

        $status = strtoupper($data['transaction']['status'] ?? '');

        if ($status === 'TS') { // Transaction Successful
            $payment->markAsCompleted($data['transaction']['airtel_money_id'] ?? null);
            SendPaymentConfirmationJob::dispatch($payment);
            Log::info('Airtel payment completed', ['payment_id' => $payment->id]);
        } elseif (in_array($status, ['TF', 'TIP'])) { // Failed or In Progress
            if ($status === 'TF') {
                $payment->markAsFailed();
                Log::info('Airtel payment failed', ['payment_id' => $payment->id]);
            }
        }
    }

    /**
     * Check payment status.
     */
    public function checkStatus(Payment $payment): array
    {
        if ($payment->isCompleted()) {
            return [
                'status' => 'completed',
                'message' => 'Paiement effectué avec succès.',
            ];
        }

        if ($payment->isFailed()) {
            return [
                'status' => 'failed',
                'message' => 'Le paiement a échoué.',
            ];
        }

        // Check with provider
        $providerStatus = $this->checkProviderStatus($payment);

        return [
            'status' => $payment->status,
            'message' => $this->getStatusMessage($payment->status),
            'provider_status' => $providerStatus,
        ];
    }

    /**
     * Check status with payment provider.
     */
    protected function checkProviderStatus(Payment $payment): ?string
    {
        if (!$payment->transaction_reference) {
            return null;
        }

        try {
            if ($payment->payment_method === 'mtn_mobile_money') {
                return $this->checkMtnStatus($payment->transaction_reference);
            } elseif ($payment->payment_method === 'airtel_money') {
                return $this->checkAirtelStatus($payment->transaction_reference);
            }
        } catch (\Exception $e) {
            Log::error('Status check failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Check MTN payment status via direct HTTP call.
     */
    protected function checkMtnStatus(string $reference): ?string
    {
        try {
            $config    = config('partyplanner.payments.mtn_mobile_money');
            $baseUrl   = MomoApi::getBaseUrl($config['environment']);
            $timeout   = (int) ($config['http']['timeout'] ?? 30);
            $verifySsl = $config['http']['verify_ssl'] ?? true;

            /** @var \Illuminate\Http\Client\Response $tokenResponse */
            $tokenResponse = Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->when(!$verifySsl && !app()->isProduction(), fn ($h) => $h->withoutVerifying())
                ->withBasicAuth($config['api_user'], $config['api_key'])
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $config['subscription_key']])
                ->post('/collection/token/');

            if (!$tokenResponse->successful()) {
                return null;
            }

            $accessToken = $tokenResponse->json('access_token');

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->when(!$verifySsl && !app()->isProduction(), fn ($h) => $h->withoutVerifying())
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $config['subscription_key'],
                    'X-Target-Environment'      => $config['environment'],
                    'Authorization'             => 'Bearer ' . $accessToken,
                ])
                ->get('/collection/v1_0/requesttopay/' . $reference);

            if ($response->successful()) {
                return $response->json('status');
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('MTN status check failed', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check Airtel payment status.
     */
    protected function checkAirtelStatus(string $reference): ?string
    {
        $config = config('partyplanner.payments.airtel_money');

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAirtelAccessToken(),
        ])->get($config['api_url'] . "/standard/v1/payments/{$reference}");

        if ($response->successful()) {
            return $response->json('data.transaction.status');
        }

        return null;
    }

    /**
     * Create a payment record.
     */
    protected function createPayment(Subscription $subscription, string $method, ?string $idempotencyKey = null): Payment
    {
        return Payment::create([
            'subscription_id' => $subscription->id,
            'idempotency_key' => $idempotencyKey,
            'amount' => $subscription->total_price,
            'currency' => config('partyplanner.currency.code', 'XAF'),
            'payment_method' => $method,
            'status' => 'pending',
            'metadata' => [
                'subscription_id' => $subscription->id,
                'event_id' => $subscription->event_id,
                'plan_type' => $subscription->plan_type,
            ],
        ]);
    }

    /**
     * Get Airtel access token.
     */
    protected function getAirtelAccessToken(): string
    {
        $config = config('partyplanner.payments.airtel_money');

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($config['api_url'] . '/auth/oauth2/token', [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        throw new \Exception('Unable to get Airtel access token');
    }

    /**
     * Validate MTN phone number (Congo: starts with 06).
     * Bypassed in sandbox mode to allow test numbers.
     */
    protected function isValidMtnNumber(string $phone): bool
    {
        // Bypass validation in sandbox mode for test numbers
        $config = config('partyplanner.payments.mtn_mobile_money');
        if ($config['environment'] === 'sandbox') {
            return true;
        }

        $phone = $this->formatPhoneNumber($phone);
        // Congo: +24206XXXXXXXX (9 digits after country code, starting with 06)
        return preg_match('/^2420?6\d{7}$/', $phone);
    }

    /**
     * Validate Airtel phone number (Congo: starts with 04 or 05).
     */
    protected function isValidAirtelNumber(string $phone): bool
    {
        $phone = $this->formatPhoneNumber($phone);
        // Congo: +24204XXXXXXXX or +24205XXXXXXXX (9 digits after country code, starting with 04 or 05)
        return preg_match('/^2420?[45]\d{7}$/', $phone);
    }

    /**
     * Format phone number to international format (Congo: +242).
     * In sandbox mode, test numbers are returned as-is.
     */
    protected function formatPhoneNumber(string $phone, bool $forMtn = false): string
    {
        // Remove spaces, dashes, and other characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // In sandbox mode, return test numbers as-is (they start with 467)
        if ($forMtn) {
            $config = config('partyplanner.payments.mtn_mobile_money');
            if ($config['environment'] === 'sandbox' && str_starts_with($phone, '467')) {
                return $phone;
            }
        }

        // If starts with 242, keep as is
        if (str_starts_with($phone, '242')) {
            return $phone;
        }

        // If starts with 0 and has 9 digits, add country code 242
        if (strlen($phone) === 9 && str_starts_with($phone, '0')) {
            return '242' . substr($phone, 1);
        }

        // If has 8 digits and starts with 6, 4, or 5, add 2420
        if (strlen($phone) === 8 && preg_match('/^[456]/', $phone)) {
            return '2420' . $phone;
        }

        return $phone;
    }

    /**
     * Simulate MTN payment for local development.
     */
    protected function simulateMtnPayment(Payment $payment, string $phoneNumber): array
    {
        $externalId = 'SIM-' . Str::uuid()->toString();

        $payment->update([
            'transaction_reference' => $externalId,
            'metadata' => array_merge($payment->metadata ?? [], [
                'phone_number' => $phoneNumber,
                'external_id' => $externalId,
                'initiated_at' => now()->toIso8601String(),
                'simulated' => true,
            ]),
        ]);

        // Auto-complete after 3 seconds (simulates user confirmation)
        // In real scenario, you'd wait for callback
        if (str_ends_with($phoneNumber, '0')) {
            // Numbers ending in 0 = success (like test number 46733123450)
            $payment->markAsCompleted('SIM-TXN-' . Str::random(10));
            Log::info('Simulated MTN payment completed', ['payment_id' => $payment->id]);
        } elseif (str_ends_with($phoneNumber, '1')) {
            // Numbers ending in 1 = failed
            $payment->markAsFailed();
            Log::info('Simulated MTN payment failed', ['payment_id' => $payment->id]);
        }
        // Numbers ending in other digits = stay pending

        return [
            'success' => true,
            'message' => '[SIMULATION] Paiement initié. Statut simulé automatiquement.',
            'payment' => $payment->fresh(),
            'reference' => $externalId,
        ];
    }

    /**
     * Get status message.
     */
    protected function getStatusMessage(string $status): string
    {
        return match ($status) {
            'pending' => 'Paiement en attente de confirmation.',
            'completed' => 'Paiement effectué avec succès.',
            'failed' => 'Le paiement a échoué.',
            'refunded' => 'Le paiement a été remboursé.',
            default => 'Statut inconnu.',
        };
    }

    /**
     * Get payment statistics.
     */
    public function getStatistics(): array
    {
        $payments = Payment::all();

        return [
            'total' => $payments->count(),
            'completed' => $payments->where('status', 'completed')->count(),
            'pending' => $payments->where('status', 'pending')->count(),
            'failed' => $payments->where('status', 'failed')->count(),
            'total_amount' => $payments->where('status', 'completed')->sum('amount'),
            'by_method' => $payments->groupBy('payment_method')->map->count()->toArray(),
        ];
    }
}
