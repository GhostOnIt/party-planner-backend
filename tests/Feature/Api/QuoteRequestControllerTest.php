<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_business_quote_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        QuoteRequestStage::create([
            'name' => 'Nouvelle',
            'slug' => 'new',
            'sort_order' => 0,
            'is_active' => true,
            'is_system' => true,
        ]);

        $plan = Plan::query()->create([
            'name' => 'Business',
            'slug' => 'business',
            'price' => 0,
            'duration_days' => 30,
            'is_trial' => false,
            'is_active' => true,
            'sort_order' => 99,
            'limits' => [],
            'features' => ['sales.contact_required' => true],
        ]);

        $response = $this->postJson('/api/quote-requests', [
            'plan_id' => $plan->id,
            'contact_name' => 'Jean Client',
            'contact_email' => 'jean.client@example.com',
            'contact_phone' => '+242060000000',
            'company_name' => 'Acme Events',
            'business_needs' => 'Nous organisons des événements corporate à fort volume.',
            'budget_estimate' => 2000000,
            'team_size' => 25,
            'timeline' => 'Sous 2 mois',
            'event_types' => ['Corporate'],
        ]);

        $response->assertCreated()->assertJsonStructure([
            'data' => ['id', 'tracking_code', 'current_stage_id'],
        ]);
    }

    public function test_admin_can_update_quote_request_stage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $firstStage = QuoteRequestStage::create(['name' => 'Nouvelle', 'slug' => 'new', 'sort_order' => 0]);
        $secondStage = QuoteRequestStage::create(['name' => 'Qualifiée', 'slug' => 'qualified', 'sort_order' => 1]);

        $quoteRequest = QuoteRequest::create([
            'tracking_code' => 'QR-TEST001',
            'status' => 'open',
            'contact_name' => 'Client',
            'contact_email' => 'client@test.com',
            'contact_phone' => '+242060000001',
            'company_name' => 'Acme',
            'business_needs' => 'Besoin de pilotage centralisé pour plusieurs événements.',
            'current_stage_id' => $firstStage->id,
        ]);

        $response = $this->patchJson("/api/admin/quote-requests/{$quoteRequest->id}/stage", [
            'stage_id' => $secondStage->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quote_requests', [
            'id' => $quoteRequest->id,
            'current_stage_id' => $secondStage->id,
        ]);
    }
}
