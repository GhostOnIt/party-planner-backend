<?php

namespace App\Services;

use App\Jobs\SendOtpJob;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        protected TwilioService $twilioService
    ) {}

    /**
     * Generate a new OTP.
     */
    public function generate(
        string $identifier,
        string $type,
        string $channel,
        ?int $userId = null
    ): Otp {
        // Invalidate any existing OTPs for this identifier and type
        $this->invalidateExisting($identifier, $type);

        // Generate a 6-digit code
        $code = $this->generateCode();

        // Create the OTP record
        $otp = Otp::create([
            'user_id' => $userId,
            'identifier' => $identifier,
            'code' => $code,
            'type' => $type,
            'channel' => $channel,
            'expires_at' => now()->addMinutes(Otp::EXPIRATION_MINUTES),
        ]);

        Log::info('OTP generated', [
            'otp_id' => $otp->id,
            'identifier' => $this->maskIdentifier($identifier),
            'type' => $type,
            'channel' => $channel,
        ]);

        return $otp;
    }

    /**
     * Send OTP via the specified channel.
     */
    public function send(Otp $otp): array
    {
        return match ($otp->channel) {
            Otp::CHANNEL_EMAIL => $this->sendViaEmail($otp),
            Otp::CHANNEL_SMS => $this->sendViaSms($otp),
            Otp::CHANNEL_WHATSAPP => $this->sendViaWhatsApp($otp),
            default => ['success' => false, 'message' => 'Invalid channel'],
        };
    }

    /**
     * Send OTP asynchronously via queue.
     */
    public function sendAsync(Otp $otp): void
    {
        SendOtpJob::dispatch($otp);
    }

    /**
     * Generate and send OTP in one step.
     */
    public function generateAndSend(
        string $identifier,
        string $type,
        string $channel,
        ?int $userId = null,
        bool $async = true
    ): Otp {
        $otp = $this->generate($identifier, $type, $channel, $userId);

        if ($async) {
            $this->sendAsync($otp);
        } else {
            $this->send($otp);
        }

        return $otp;
    }

    /**
     * Verify an OTP code.
     */
    public function verify(string $identifier, string $code, string $type): array
    {
        $otp = Otp::forIdentifier($identifier, $type)
            ->valid()
            ->latest()
            ->first();

        if (!$otp) {
            Log::warning('OTP verification failed - no valid OTP found', [
                'identifier' => $this->maskIdentifier($identifier),
                'type' => $type,
            ]);

            return [
                'success' => false,
                'message' => 'Code invalide ou expiré.',
                'error' => 'otp_not_found',
            ];
        }

        // Check if max attempts exceeded
        if ($otp->hasExceededAttempts()) {
            Log::warning('OTP verification failed - max attempts exceeded', [
                'otp_id' => $otp->id,
                'attempts' => $otp->attempts,
            ]);

            return [
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez demander un nouveau code.',
                'error' => 'max_attempts_exceeded',
            ];
        }

        // Verify the code
        if ($otp->code !== $code) {
            $otp->incrementAttempts();

            $remainingAttempts = Otp::MAX_ATTEMPTS - $otp->attempts;

            Log::warning('OTP verification failed - invalid code', [
                'otp_id' => $otp->id,
                'attempts' => $otp->attempts,
                'remaining' => $remainingAttempts,
            ]);

            return [
                'success' => false,
                'message' => "Code incorrect. Il vous reste {$remainingAttempts} tentative(s).",
                'error' => 'invalid_code',
                'remaining_attempts' => $remainingAttempts,
            ];
        }

        // Mark as verified
        $otp->markAsVerified();

        Log::info('OTP verified successfully', [
            'otp_id' => $otp->id,
            'identifier' => $this->maskIdentifier($identifier),
            'type' => $type,
        ]);

        return [
            'success' => true,
            'message' => 'Code vérifié avec succès.',
            'otp' => $otp,
            'user_id' => $otp->user_id,
        ];
    }

    /**
     * Resend an OTP.
     */
    public function resend(int $otpId): array
    {
        $otp = Otp::find($otpId);

        if (!$otp) {
            return [
                'success' => false,
                'message' => 'OTP non trouvé.',
                'error' => 'otp_not_found',
            ];
        }

        // Check if already verified
        if ($otp->isVerified()) {
            return [
                'success' => false,
                'message' => 'Ce code a déjà été vérifié.',
                'error' => 'already_verified',
            ];
        }

        // Generate a new OTP with same parameters
        $newOtp = $this->generate(
            $otp->identifier,
            $otp->type,
            $otp->channel,
            $otp->user_id
        );

        // Send asynchronously
        $this->sendAsync($newOtp);

        return [
            'success' => true,
            'message' => 'Un nouveau code a été envoyé.',
            'otp' => $newOtp,
        ];
    }

    /**
     * Cleanup expired OTPs.
     */
    public function cleanup(): int
    {
        $count = Otp::where('expires_at', '<', now()->subHours(24))->delete();

        Log::info('Expired OTPs cleaned up', ['count' => $count]);

        return $count;
    }

    /**
     * Send OTP via email.
     */
    protected function sendViaEmail(Otp $otp): array
    {
        try {
            $user = $otp->user ?? User::where('email', $otp->identifier)->first();

            if ($user) {
                $user->notify(new \App\Notifications\OtpNotification($otp));
            } else {
                // Send to email directly for registration
                \Illuminate\Support\Facades\Notification::route('mail', $otp->identifier)
                    ->notify(new \App\Notifications\OtpNotification($otp));
            }

            Log::info('OTP email sent', [
                'otp_id' => $otp->id,
                'identifier' => $this->maskIdentifier($otp->identifier),
            ]);

            return ['success' => true, 'message' => 'Email envoyé'];
        } catch (\Exception $e) {
            Log::error('OTP email failed', [
                'otp_id' => $otp->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send OTP via SMS.
     */
    protected function sendViaSms(Otp $otp): array
    {
        $message = $this->getOtpMessage($otp);

        $result = $this->twilioService->sendSms($otp->identifier, $message);

        if ($result['success']) {
            Log::info('OTP SMS sent', [
                'otp_id' => $otp->id,
                'identifier' => $this->maskIdentifier($otp->identifier),
            ]);
        } else {
            Log::error('OTP SMS failed', [
                'otp_id' => $otp->id,
                'error' => $result['message'] ?? 'Unknown error',
            ]);
        }

        return $result;
    }

    /**
     * Send OTP via WhatsApp.
     */
    protected function sendViaWhatsApp(Otp $otp): array
    {
        $message = $this->getOtpMessage($otp);

        $result = $this->twilioService->sendWhatsApp($otp->identifier, $message);

        if ($result['success']) {
            Log::info('OTP WhatsApp sent', [
                'otp_id' => $otp->id,
                'identifier' => $this->maskIdentifier($otp->identifier),
            ]);
        } else {
            Log::error('OTP WhatsApp failed', [
                'otp_id' => $otp->id,
                'error' => $result['message'] ?? 'Unknown error',
            ]);
        }

        return $result;
    }

    /**
     * Generate a 4-digit OTP code.
     */
    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Invalidate existing OTPs for identifier and type.
     */
    protected function invalidateExisting(string $identifier, string $type): void
    {
        Otp::forIdentifier($identifier, $type)
            ->whereNull('verified_at')
            ->update(['expires_at' => now()]);
    }

    /**
     * Get the OTP message content.
     */
    protected function getOtpMessage(Otp $otp): string
    {
        $appName = config('app.name', 'Party Planner');
        $typeLabel = $this->getTypeLabel($otp->type);

        return sprintf(
            "%s - Votre code de %s est: %s\n\nCe code expire dans %d minutes.\n\nNe partagez ce code avec personne.",
            $appName,
            $typeLabel,
            $otp->code,
            Otp::EXPIRATION_MINUTES
        );
    }

    /**
     * Get human-readable type label.
     */
    protected function getTypeLabel(string $type): string
    {
        return match ($type) {
            Otp::TYPE_REGISTRATION => 'vérification',
            Otp::TYPE_LOGIN => 'connexion',
            Otp::TYPE_PASSWORD_RESET => 'réinitialisation',
            default => 'vérification',
        };
    }

    /**
     * Mask identifier for logging (privacy).
     */
    protected function maskIdentifier(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier);
            $name = $parts[0];
            $domain = $parts[1];
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
            return $maskedName . '@' . $domain;
        }

        // Phone number
        return substr($identifier, 0, 4) . str_repeat('*', max(0, strlen($identifier) - 6)) . substr($identifier, -2);
    }
}
