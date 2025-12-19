<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

class TwilioWebhookController extends Controller
{
    /**
     * Handle Twilio SMS status callback.
     */
    public function handleSmsStatus(Request $request): Response
    {
        if (!$this->validateTwilioRequest($request)) {
            Log::warning('Twilio SMS webhook: Invalid signature');
            return response('Invalid signature', 401);
        }

        $messageSid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');
        $errorCode = $request->input('ErrorCode');

        Log::info('Twilio SMS status', [
            'message_sid' => $messageSid,
            'status' => $status,
            'error_code' => $errorCode,
        ]);

        // Handle different statuses
        match ($status) {
            'delivered' => $this->handleDelivered($messageSid),
            'failed', 'undelivered' => $this->handleFailed($messageSid, $errorCode),
            default => null,
        };

        return response('OK', 200);
    }

    /**
     * Handle Twilio WhatsApp status callback.
     */
    public function handleWhatsAppStatus(Request $request): Response
    {
        if (!$this->validateTwilioRequest($request)) {
            Log::warning('Twilio WhatsApp webhook: Invalid signature');
            return response('Invalid signature', 401);
        }

        $messageSid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');
        $errorCode = $request->input('ErrorCode');

        Log::info('Twilio WhatsApp status', [
            'message_sid' => $messageSid,
            'status' => $status,
            'error_code' => $errorCode,
        ]);

        return response('OK', 200);
    }

    /**
     * Handle incoming SMS messages.
     */
    public function handleIncomingSms(Request $request): Response
    {
        if (!$this->validateTwilioRequest($request)) {
            Log::warning('Twilio incoming SMS webhook: Invalid signature');
            return response('Invalid signature', 401);
        }

        $from = $request->input('From');
        $body = $request->input('Body');
        $messageSid = $request->input('MessageSid');

        Log::info('Incoming SMS', [
            'from' => $from,
            'body' => $body,
            'message_sid' => $messageSid,
        ]);

        // Process incoming message (e.g., RSVP responses)
        $this->processIncomingMessage($from, $body);

        // Return TwiML response
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Handle incoming WhatsApp messages.
     */
    public function handleIncomingWhatsApp(Request $request): Response
    {
        if (!$this->validateTwilioRequest($request)) {
            Log::warning('Twilio incoming WhatsApp webhook: Invalid signature');
            return response('Invalid signature', 401);
        }

        $from = $request->input('From');
        $body = $request->input('Body');
        $messageSid = $request->input('MessageSid');

        // Remove whatsapp: prefix for processing
        $phoneNumber = str_replace('whatsapp:', '', $from);

        Log::info('Incoming WhatsApp', [
            'from' => $phoneNumber,
            'body' => $body,
            'message_sid' => $messageSid,
        ]);

        // Process incoming message
        $this->processIncomingMessage($phoneNumber, $body);

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Validate Twilio request signature.
     */
    protected function validateTwilioRequest(Request $request): bool
    {
        $authToken = config('services.twilio.token');

        // Skip validation in development if no token configured
        if (empty($authToken) || app()->environment('local')) {
            return true;
        }

        $signature = $request->header('X-Twilio-Signature');
        if (!$signature) {
            return false;
        }

        $validator = new RequestValidator($authToken);
        $url = $request->fullUrl();
        $params = $request->all();

        return $validator->validate($signature, $url, $params);
    }

    /**
     * Handle delivered message.
     */
    protected function handleDelivered(string $messageSid): void
    {
        // You can update message status in database if tracking
        Log::info('Message delivered', ['sid' => $messageSid]);
    }

    /**
     * Handle failed message.
     */
    protected function handleFailed(string $messageSid, ?string $errorCode): void
    {
        Log::warning('Message failed', [
            'sid' => $messageSid,
            'error_code' => $errorCode,
        ]);
        // Implement retry logic or notification here
    }

    /**
     * Process incoming message (RSVP responses, etc.).
     */
    protected function processIncomingMessage(string $from, string $body): void
    {
        $body = strtolower(trim($body));

        // Simple RSVP keyword detection
        $confirmKeywords = ['oui', 'yes', 'confirme', 'confirm', 'je viens', 'present'];
        $declineKeywords = ['non', 'no', 'decline', 'refuse', 'ne viens pas', 'absent'];

        foreach ($confirmKeywords as $keyword) {
            if (str_contains($body, $keyword)) {
                $this->handleRsvpResponse($from, 'confirmed');
                return;
            }
        }

        foreach ($declineKeywords as $keyword) {
            if (str_contains($body, $keyword)) {
                $this->handleRsvpResponse($from, 'declined');
                return;
            }
        }

        // Unknown message - could send help response
        Log::info('Unknown incoming message', ['from' => $from, 'body' => $body]);
    }

    /**
     * Handle RSVP response from incoming message.
     */
    protected function handleRsvpResponse(string $phoneNumber, string $status): void
    {
        Log::info('RSVP response received', [
            'phone' => $phoneNumber,
            'status' => $status,
        ]);

        // Find guest by phone number and update status
        // This would need to be implemented based on your Guest model structure
        // Guest::where('phone', $phoneNumber)->update(['status' => $status]);
    }
}
