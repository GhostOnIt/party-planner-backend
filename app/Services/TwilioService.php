<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Jobs\SendWhatsAppJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class TwilioService
{
    protected ?Client $client = null;
    protected int $maxRetries = 3;
    protected int $retryDelay = 5; // seconds

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Check if Twilio is configured.
     */
    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    /**
     * Send SMS message.
     */
    public function sendSms(
        string $to,
        string $message,
        ?string $from = null,
        int $attempt = 1
    ): array {
        if (!$this->isConfigured()) {
            Log::warning('Twilio not configured, skipping SMS');
            return ['success' => false, 'message' => 'Twilio not configured'];
        }

        $from = $from ?? config('services.twilio.from');

        if (!$from) {
            return ['success' => false, 'message' => 'No sender number configured'];
        }

        try {
            $to = $this->formatPhoneNumber($to);

            $result = $this->client->messages->create($to, [
                'from' => $from,
                'body' => $message,
            ]);

            Log::info('SMS sent', [
                'to' => $to,
                'sid' => $result->sid,
                'status' => $result->status,
            ]);

            return [
                'success' => true,
                'message_sid' => $result->sid,
                'status' => $result->status,
            ];

        } catch (TwilioException $e) {
            Log::error('SMS failed', [
                'to' => $to,
                'error' => $e->getMessage(),
                'attempt' => $attempt,
            ]);

            // Retry logic
            if ($attempt < $this->maxRetries && $this->isRetryableError($e)) {
                sleep($this->retryDelay * $attempt);
                return $this->sendSms($to, $message, $from, $attempt + 1);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Send SMS asynchronously via queue.
     */
    public function sendSmsAsync(string $to, string $message, ?string $from = null): void
    {
        SendSmsJob::dispatch($to, $message, $from);
    }

    /**
     * Send WhatsApp message.
     */
    public function sendWhatsApp(
        string $to,
        string $message,
        ?string $from = null,
        ?string $mediaUrl = null,
        int $attempt = 1
    ): array {
        if (!$this->isConfigured()) {
            Log::warning('Twilio not configured, skipping WhatsApp');
            return ['success' => false, 'message' => 'Twilio not configured'];
        }

        $from = $from ?? config('services.twilio.whatsapp_from');

        if (!$from) {
            return ['success' => false, 'message' => 'No WhatsApp sender configured'];
        }

        try {
            $to = $this->formatWhatsAppNumber($to);
            $from = $this->formatWhatsAppNumber($from, true);

            $params = [
                'from' => $from,
                'body' => $message,
            ];

            if ($mediaUrl) {
                $params['mediaUrl'] = [$mediaUrl];
            }

            $result = $this->client->messages->create($to, $params);

            Log::info('WhatsApp sent', [
                'to' => $to,
                'sid' => $result->sid,
                'status' => $result->status,
            ]);

            return [
                'success' => true,
                'message_sid' => $result->sid,
                'status' => $result->status,
            ];

        } catch (TwilioException $e) {
            Log::error('WhatsApp failed', [
                'to' => $to,
                'error' => $e->getMessage(),
                'attempt' => $attempt,
            ]);

            // Retry logic
            if ($attempt < $this->maxRetries && $this->isRetryableError($e)) {
                sleep($this->retryDelay * $attempt);
                return $this->sendWhatsApp($to, $message, $from, $mediaUrl, $attempt + 1);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Send WhatsApp asynchronously via queue.
     */
    public function sendWhatsAppAsync(
        string $to,
        string $message,
        ?string $from = null,
        ?string $mediaUrl = null
    ): void {
        SendWhatsAppJob::dispatch($to, $message, $from, $mediaUrl);
    }

    /**
     * Send templated message (WhatsApp approved templates).
     */
    public function sendWhatsAppTemplate(
        string $to,
        string $templateSid,
        array $variables = [],
        ?string $from = null
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Twilio not configured'];
        }

        $from = $from ?? config('services.twilio.whatsapp_from');

        try {
            $to = $this->formatWhatsAppNumber($to);
            $from = $this->formatWhatsAppNumber($from, true);

            $params = [
                'from' => $from,
                'contentSid' => $templateSid,
            ];

            if (!empty($variables)) {
                $params['contentVariables'] = json_encode($variables);
            }

            $result = $this->client->messages->create($to, $params);

            Log::info('WhatsApp template sent', [
                'to' => $to,
                'template' => $templateSid,
                'sid' => $result->sid,
            ]);

            return [
                'success' => true,
                'message_sid' => $result->sid,
                'status' => $result->status,
            ];

        } catch (TwilioException $e) {
            Log::error('WhatsApp template failed', [
                'to' => $to,
                'template' => $templateSid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send bulk SMS to multiple recipients.
     */
    public function sendBulkSms(array $recipients, string $message): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($recipients as $to) {
            $result = $this->sendSms($to, $message);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'to' => $to,
                    'error' => $result['message'] ?? 'Unknown error',
                ];
            }
        }

        return $results;
    }

    /**
     * Get message status.
     */
    public function getMessageStatus(string $messageSid): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Twilio not configured'];
        }

        try {
            $message = $this->client->messages($messageSid)->fetch();

            return [
                'success' => true,
                'status' => $message->status,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
                'date_sent' => $message->dateSent?->format('Y-m-d H:i:s'),
                'date_updated' => $message->dateUpdated?->format('Y-m-d H:i:s'),
            ];

        } catch (TwilioException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate phone number.
     */
    public function validatePhoneNumber(string $phoneNumber): array
    {
        if (!$this->isConfigured()) {
            return ['valid' => false, 'message' => 'Twilio not configured'];
        }

        try {
            $lookup = $this->client->lookups->v2->phoneNumbers($phoneNumber)->fetch();

            return [
                'valid' => $lookup->valid,
                'phone_number' => $lookup->phoneNumber,
                'country_code' => $lookup->countryCode,
                'caller_name' => $lookup->callerName ?? null,
            ];

        } catch (TwilioException $e) {
            return [
                'valid' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format phone number to E.164 format.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove spaces, dashes, and other characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Add + if not present and starts with country code
        if (!str_starts_with($phone, '+')) {
            // Assume Cameroon number if starts with 6
            if (str_starts_with($phone, '6') && strlen($phone) === 9) {
                $phone = '+242' . $phone;
            } elseif (str_starts_with($phone, '237')) {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Format phone number for WhatsApp.
     */
    protected function formatWhatsAppNumber(string $phone, bool $isSender = false): string
    {
        $phone = $this->formatPhoneNumber($phone);

        // WhatsApp numbers need whatsapp: prefix
        if (!str_starts_with($phone, 'whatsapp:')) {
            $phone = 'whatsapp:' . $phone;
        }

        return $phone;
    }

    /**
     * Check if error is retryable.
     */
    protected function isRetryableError(TwilioException $e): bool
    {
        $retryableCodes = [
            20429, // Too Many Requests
            20503, // Service Unavailable
            30002, // Account Suspended (temporary)
        ];

        return in_array($e->getCode(), $retryableCodes);
    }

    // ===== Message Templates =====

    /**
     * Get invitation message template.
     */
    public function getInvitationTemplate(array $data): string
    {
        return sprintf(
            "Vous etes invite a l'evenement \"%s\"!\n\n" .
            "Date: %s\n" .
            "Lieu: %s\n\n" .
            "Repondez via le lien: %s\n\n" .
            "Party Planner",
            $data['event_title'],
            $data['event_date'],
            $data['event_location'] ?? 'A confirmer',
            $data['invitation_url']
        );
    }

    /**
     * Get reminder message template.
     */
    public function getReminderTemplate(array $data): string
    {
        return sprintf(
            "Rappel: L'evenement \"%s\" approche!\n\n" .
            "Date: %s\n" .
            "Dans: %s\n\n" .
            "Party Planner",
            $data['event_title'],
            $data['event_date'],
            $data['time_until']
        );
    }

    /**
     * Get payment confirmation template.
     */
    public function getPaymentConfirmationTemplate(array $data): string
    {
        return sprintf(
            "Paiement confirme!\n\n" .
            "Montant: %s %s\n" .
            "Reference: %s\n" .
            "Evenement: %s\n\n" .
            "Merci pour votre confiance!\n" .
            "Party Planner",
            number_format($data['amount'], 0, ',', ' '),
            $data['currency'],
            $data['reference'],
            $data['event_title']
        );
    }

    /**
     * Get RSVP confirmation template.
     */
    public function getRsvpConfirmationTemplate(array $data): string
    {
        $status = $data['status'] === 'confirmed' ? 'confirmee' : 'declinee';
        return sprintf(
            "Votre reponse a ete enregistree: %s\n\n" .
            "Evenement: %s\n" .
            "Date: %s\n\n" .
            "Party Planner",
            $status,
            $data['event_title'],
            $data['event_date']
        );
    }

    /**
     * Send event invitation SMS.
     */
    public function sendInvitationSms(string $to, array $eventData): array
    {
        $message = $this->getInvitationTemplate($eventData);
        return $this->sendSms($to, $message);
    }

    /**
     * Send event invitation WhatsApp.
     */
    public function sendInvitationWhatsApp(string $to, array $eventData): array
    {
        $message = $this->getInvitationTemplate($eventData);
        return $this->sendWhatsApp($to, $message);
    }

    /**
     * Send event reminder SMS.
     */
    public function sendReminderSms(string $to, array $eventData): array
    {
        $message = $this->getReminderTemplate($eventData);
        return $this->sendSms($to, $message);
    }

    /**
     * Send payment confirmation SMS.
     */
    public function sendPaymentSms(string $to, array $paymentData): array
    {
        $message = $this->getPaymentConfirmationTemplate($paymentData);
        return $this->sendSms($to, $message);
    }
}
