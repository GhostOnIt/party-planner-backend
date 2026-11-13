<?php

namespace Tests\Feature;

use App\Services\OtpService;
use App\Services\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StagingOtpTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OtpService
    {
        // Make the random-code fallback deterministic, so negative cases cannot
        // accidentally draw 1111 and produce a flaky security regression test.
        return new class (Mockery::mock(TwilioService::class)) extends OtpService {
            protected function generateCode(): string
            {
                return '2468';
            }
        };
    }

    private function configure(string $environment = 'staging', bool $enabled = true): void
    {
        $this->app->instance('env', $environment);
        config([
            'staging.fixed_login_otp_enabled' => $enabled,
            'staging.otp_allowed_emails' => ['alexsonicka11@gmail.com'],
        ]);
    }

    public static function generationCases(): array
    {
        return [
            'allowed staging email login' => ['staging', true, 'alexsonicka11@gmail.com', 'login', 'email', '1111'],
            'normalized email' => ['staging', true, 'ALEXSONICKA11@gmail.com', 'login', 'email', '1111'],
            'production always uses random fallback' => ['production', true, 'alexsonicka11@gmail.com', 'login', 'email', '2468'],
            'disabled switch' => ['staging', false, 'alexsonicka11@gmail.com', 'login', 'email', '2468'],
            'other account' => ['staging', true, 'other@example.com', 'login', 'email', '2468'],
            'registration' => ['staging', true, 'alexsonicka11@gmail.com', 'registration', 'email', '2468'],
            'password reset' => ['staging', true, 'alexsonicka11@gmail.com', 'password_reset', 'email', '2468'],
            'sms' => ['staging', true, 'alexsonicka11@gmail.com', 'login', 'sms', '2468'],
        ];
    }

    #[DataProvider('generationCases')]
    public function test_code_generation_is_restricted(
        string $environment, bool $enabled, string $identifier, string $type, string $channel, string $expected
    ): void {
        $this->configure($environment, $enabled);
        $otp = $this->service()->generate($identifier, $type, $channel);

        $this->assertSame($expected, $otp->fresh()->code);
        $this->assertTrue($otp->expires_at->isFuture());
    }

    public function test_fixed_code_requires_a_request_and_is_consumed(): void
    {
        $this->configure();
        $service = $this->service();
        $email = 'alexsonicka11@gmail.com';

        $this->assertFalse($service->verify($email, '1111', 'login')['success']);
        $service->generate($email, 'login', 'email');
        $this->assertTrue($service->verify($email, '1111', 'login')['success']);
        $this->assertFalse($service->verify($email, '1111', 'login')['success']);
    }

    public function test_fixed_code_still_expires(): void
    {
        $this->configure();
        $service = $this->service();
        $otp = $service->generate('alexsonicka11@gmail.com', 'login', 'email');
        $otp->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($service->verify($otp->identifier, '1111', 'login')['success']);
    }
}
