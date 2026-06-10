<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PawaPayService
{
    private const BEARER_PREFIX = 'Bearer ';

    public function initiateDeposit(
        Subscription $subscription,
        Payment $payment,
        string $phoneNumber,
        string $provider,
        string $country,
        ?string $currency = null
    ): array {
        $config = config('partyplanner.payments.pawapay');

        if (!($config['enabled'] ?? false)) {
            return [
                'success' => false,
                'message' => 'pawaPay n\'est pas disponible actuellement.',
            ];
        }

        if (empty($config['api_token'])) {
            return [
                'success' => false,
                'message' => 'Le token API pawaPay n\'est pas configuré.',
            ];
        }

        $depositId = $payment->transaction_reference ?: (string) Str::uuid();
        $currency = strtoupper($currency ?: ($config['currency'] ?? $payment->currency));
        $amount = $this->formatAmount($payment->amount, $currency);
        $msisdn = $this->normalizeMsisdn($phoneNumber, $country);

        $payload = [
            'depositId' => $depositId,
            'amount' => $amount,
            'currency' => $currency,
            'payer' => [
                'type' => 'MMO',
                'accountDetails' => [
                    'phoneNumber' => $msisdn,
                    'provider' => $provider,
                ],
            ],
        ];

        Log::info('pawaPay deposit request', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'deposit_id' => $depositId,
            'country' => $country,
            'provider' => $provider,
            'currency' => $currency,
            'amount' => $amount,
        ]);

        /** @var Response $response */
        $response = $this->client()->post('/v2/deposits', $payload);

        if ($response->successful()) {
            $payment->update([
                'transaction_reference' => $depositId,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'pawapay' => [
                        'deposit_id' => $depositId,
                        'provider' => $provider,
                        'country' => $country,
                        'phone_number' => $msisdn,
                        'initiation_response' => $response->json(),
                        'initiated_at' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            return [
                'success' => true,
                'message' => 'Paiement pawaPay initié.',
                'payment' => $payment->fresh(),
                'reference' => $depositId,
                'provider_response' => $response->json(),
            ];
        }

        Log::error('pawaPay deposit rejected', [
            'payment_id' => $payment->id,
            'deposit_id' => $depositId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'success' => false,
            'message' => 'pawaPay a refusé la demande de paiement (code ' . $response->status() . ').',
            'status' => $response->status(),
            'body' => $response->json() ?: $response->body(),
        ];
    }

    public function getDepositStatus(string $depositId): ?array
    {
        /** @var Response $response */
        $response = $this->client()->get('/v2/deposits/' . $depositId);

        if ($response->successful()) {
            return $response->json();
        }

        Log::warning('pawaPay deposit status check failed', [
            'deposit_id' => $depositId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    public function activeConfiguration(?string $country = null, string $operationType = 'DEPOSIT'): ?array
    {
        $query = ['operationType' => $operationType];
        if ($country) {
            $query['country'] = strtoupper($country);
        }

        /** @var Response $response */
        $response = $this->client()->get('/v2/active-conf', $query);

        if ($response->successful()) {
            return $response->json();
        }

        Log::warning('pawaPay active configuration check failed', [
            'country' => $country,
            'operation_type' => $operationType,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    public function normalizeStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'COMPLETED' => 'completed',
            'FAILED' => 'failed',
            'ACCEPTED', 'SUBMITTED' => 'pending',
            default => 'unknown',
        };
    }

    public function normalizeMsisdn(string $phoneNumber, string $country = 'COG'): string
    {
        $phone = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '242')) {
            return $phone;
        }

        if (strtoupper($country) === 'COG') {
            return '242' . ltrim($phone, '0');
        }

        return $phone;
    }

    private function formatAmount(mixed $amount, string $currency): string
    {
        $numeric = (float) $amount;

        if (in_array($currency, ['XAF', 'RWF', 'UGX'], true)) {
            return (string) ((int) round($numeric));
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $config = config('partyplanner.payments.pawapay');

        return Http::baseUrl(rtrim((string) $config['base_url'], '/'))
            ->timeout((int) ($config['timeout'] ?? 30))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => self::BEARER_PREFIX . $config['api_token'],
            ]);
    }
}
