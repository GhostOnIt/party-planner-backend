<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Mail\PilotFeedbackMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_feedback_and_mail_is_dispatched(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'message' => 'Super app, merci pour la phase pilote.',
        ]);

        $response->assertOk()->assertJsonFragment([
            'message' => 'Merci, votre message a été envoyé.',
        ]);

        Mail::assertSent(PilotFeedbackMail::class, function (PilotFeedbackMail $mail) use ($user): bool {
            return $mail->user->is($user)
                && $mail->feedbackBody === 'Super app, merci pour la phase pilote.';
        });
    }

    public function test_guest_cannot_send_feedback(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/feedback', [
            'message' => 'Hello',
        ]);

        $response->assertUnauthorized();
        Mail::assertNothingSent();
    }

    public function test_empty_message_returns_validation_error(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'message' => '',
        ]);

        $response->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_admin_cannot_send_pilot_feedback(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/feedback', [
            'message' => 'Test depuis admin',
        ]);

        $response->assertForbidden();
        Mail::assertNothingSent();
    }
}
