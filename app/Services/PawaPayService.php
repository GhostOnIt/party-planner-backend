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
        ?string $provider,
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
        $country = $this->normalizeCountry($country ?: ($config['default_country'] ?? 'COG'));
        $market = $this->market($country);

        if (!$market) {
            return [
                'success' => false,
                'message' => 'Pays non supporté pour le paiement Mobile Money.',
            ];
        }

        $provider = strtoupper((string) ($provider ?: ''));
        $providerConfig = $market['providers'][$provider] ?? null;

        if (!empty($market['providers']) && !$providerConfig) {
            return [
                'success' => false,
                'message' => 'Opérateur pawaPay indisponible pour ce pays.',
            ];
        }

        if ($provider === '') {
            return [
                'success' => false,
                'message' => 'Aucun opérateur pawaPay n\'est configuré pour ' . ($market['name'] ?? $country) . '.',
            ];
        }

        if (!$this->isValidPhoneForCountry($phoneNumber, $country)) {
            return [
                'success' => false,
                'message' => 'Numéro de téléphone invalide pour ' . ($market['name'] ?? $country) . '.',
            ];
        }

        $currency = strtoupper($currency ?: ($market['currency'] ?? $config['currency'] ?? $payment->currency));
        $amountSource = $payment->amount;
        $testAmount = $config['test_amount'] ?? null;
        $usesSandboxAmount = ($config['environment'] ?? 'sandbox') === 'sandbox'
            && $testAmount !== null
            && $testAmount !== ''
            && (float) $testAmount > 2;

        if ($usesSandboxAmount) {
            $amountSource = $config['test_amount'];
            $currency = strtoupper((string) ($config['test_currency'] ?? $currency));
        }

        $amount = $this->formatAmount($amountSource, $currency);
        $msisdn = $this->normalizeMsisdn($phoneNumber, $country);
        $customerMessage = $this->customerMessage($subscription);

        $payload = [
            'depositId' => $depositId,
            'payer' => [
                'type' => 'MMO',
                'accountDetails' => [
                    'phoneNumber' => $msisdn,
                    'provider' => $provider,
                ],
            ],
            'amount' => $amount,
            'currency' => $currency,
            'clientReferenceId' => 'SUB-' . $subscription->id,
            'customerMessage' => $customerMessage,
            'metadata' => [
                ['paymentId' => (string) $payment->id],
                ['subscriptionId' => (string) $subscription->id],
                ['country' => $country],
                ['provider' => $provider],
                ['planType' => (string) ($subscription->plan_type ?? '')],
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
        $body = $response->json() ?: [];
        $initiationStatus = strtoupper((string) ($body['status'] ?? ''));

        if ($response->successful() && in_array($initiationStatus, ['ACCEPTED', 'DUPLICATE_IGNORED'], true)) {
            $payment->update([
                'transaction_reference' => $depositId,
                'currency' => $currency,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'country' => $country,
                    'currency' => $currency,
                    'charged_amount' => $amount,
                    'original_amount' => (string) $payment->amount,
                    'provider' => $provider,
                    'phone_number' => $msisdn,
                    'pawapay' => [
                        'deposit_id' => $depositId,
                        'provider' => $provider,
                        'country' => $country,
                        'currency' => $currency,
                        'charged_amount' => $amount,
                        'original_amount' => (string) $payment->amount,
                        'phone_number' => $msisdn,
                        'initiation_status' => $initiationStatus,
                        'initiation_response' => $body,
                        'initiated_at' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            return [
                'success' => true,
                'message' => 'Paiement pawaPay initié.',
                'payment' => $payment->fresh(),
                'reference' => $depositId,
                'provider_response' => $body,
            ];
        }

        Log::error('pawaPay deposit rejected', [
            'payment_id' => $payment->id,
            'deposit_id' => $depositId,
            'status' => $response->status(),
            'body' => $response->body(),
            'initiation_status' => $initiationStatus,
        ]);

        return [
            'success' => false,
            'message' => $this->rejectionMessage($body, $response->status()),
            'status' => $response->status(),
            'body' => $body ?: $response->body(),
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
            $query['country'] = $this->normalizeCountry($country);
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
            'ACCEPTED', 'SUBMITTED', 'PROCESSING', 'DUPLICATE_IGNORED' => 'pending',
            default => 'unknown',
        };
    }

    public function normalizeMsisdn(string $phoneNumber, string $country = 'COG'): string
    {
        $country = $this->normalizeCountry($country);
        $market = $this->market($country);
        $callingCode = (string) ($market['calling_code'] ?? '');
        $phone = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if ($callingCode !== '' && str_starts_with($phone, $callingCode)) {
            return $phone;
        }

        if ($callingCode !== '') {
            return $callingCode . $phone;
        }

        return $phone;
    }

    public function publicMarketConfiguration(): array
    {
        $config = config('partyplanner.payments.pawapay');

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'default_country' => $this->normalizeCountry($config['default_country'] ?? 'COG'),
            'default_provider' => $config['default_provider'] ?? null,
            'countries' => $config['countries'] ?? [],
        ];
    }

    public function normalizeCountry(string $country): string
    {
        $country = strtoupper(trim($country));

        return match ($country) {
            'CG' => 'COG',
            'CD' => 'COD',
            'CM' => 'CMR',
            'GA' => 'GAB',
            'SN' => 'SEN',
            'CI' => 'CIV',
            default => $country,
        };
    }

    public function isValidPhoneForCountry(string $phoneNumber, string $country): bool
    {
        $country = $this->normalizeCountry($country);
        $market = $this->market($country);

        if (!$market) {
            return false;
        }

        $callingCode = (string) ($market['calling_code'] ?? '');
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $national = $digits;
        if ($callingCode !== '' && str_starts_with($digits, $callingCode)) {
            $national = substr($digits, strlen($callingCode));
        }

        $regex = $market['national_phone_regex'] ?? null;

        return is_string($regex) && preg_match($regex, $national) === 1;
    }

    private function market(string $country): ?array
    {
        $countries = config('partyplanner.payments.pawapay.countries', []);

        return $countries[$this->normalizeCountry($country)] ?? null;
    }

    private function formatAmount(mixed $amount, string $currency): string
    {
        $numeric = (float) $amount;

        if (in_array($currency, ['XAF', 'RWF', 'UGX'], true)) {
            return (string) ((int) round($numeric));
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    private function customerMessage(Subscription $subscription): string
    {
        $plan = $subscription->plan?->name ?? $subscription->plan_type ?? 'Plan';
        $message = preg_replace('/[^a-zA-Z0-9 ]/', '', 'PartyPlanner ' . $plan) ?? 'PartyPlanner';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return Str::substr($message !== '' ? $message : 'PartyPlanner', 0, 22);
    }

    private function rejectionMessage(array $body, int $httpStatus): string
    {
        $failureReason = $body['failureReason'] ?? null;
        $code = is_array($failureReason)
            ? ($failureReason['failureCode'] ?? $failureReason['code'] ?? null)
            : null;
        $message = is_array($failureReason)
            ? ($failureReason['failureMessage'] ?? $failureReason['message'] ?? null)
            : null;

        if ($message) {
            return 'pawaPay a refusé le paiement : ' . $message;
        }

        if ($code) {
            return 'pawaPay a refusé le paiement : ' . $code . '.';
        }

        return 'pawaPay a refusé la demande de paiement (code ' . $httpStatus . ').';
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
