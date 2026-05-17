<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\CustomOffer;
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

    protected QuoteRequestStage $stageNew;
    protected QuoteRequestStage $stageQualified;
    protected Plan $businessPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stageNew = QuoteRequestStage::create([
            'name' => 'Nouvelle',
            'slug' => 'new',
            'sort_order' => 0,
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->stageQualified = QuoteRequestStage::create([
            'name' => 'Qualifiée',
            'slug' => 'qualified',
            'sort_order' => 1,
            'is_active' => true,
            'is_system' => false,
        ]);

        $this->businessPlan = Plan::query()->create([
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
    }

    protected function makeQuoteRequest(array $overrides = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'tracking_code' => 'QR-' . uniqid(),
            'status' => 'open',
            'plan_id' => $this->businessPlan->id,
            'contact_name' => 'Client Test',
            'contact_email' => 'client@test.com',
            'contact_phone' => '+242060000000',
            'company_name' => 'Acme',
            'business_needs' => 'Pilotage centralisé pour plusieurs événements corporate.',
            'current_stage_id' => $this->stageNew->id,
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | User-facing endpoints
    |--------------------------------------------------------------------------
    */

    public function test_user_can_submit_business_quote_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/quote-requests', [
            'plan_id' => $this->businessPlan->id,
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

        $this->assertDatabaseHas('quote_requests', [
            'contact_email' => 'jean.client@example.com',
            'user_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_submit_quote_request(): void
    {
        $response = $this->postJson('/api/quote-requests', [
            'plan_id' => $this->businessPlan->id,
            'contact_name' => 'X',
        ]);

        $response->assertUnauthorized();
    }

    public function test_submit_quote_request_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/quote-requests', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_name', 'contact_email', 'business_needs']);
    }

    public function test_user_can_list_their_quote_requests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->makeQuoteRequest(['user_id' => $user->id]);
        $this->makeQuoteRequest(['user_id' => $user->id]);
        $this->makeQuoteRequest(); // Belongs to nobody

        $response = $this->getJson('/api/quote-requests/mine');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_list_their_offers(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $qr = $this->makeQuoteRequest(['user_id' => $user->id]);
        CustomOffer::create([
            'quote_request_id' => $qr->id,
            'created_by' => User::factory()->create(['role' => UserRole::ADMIN])->id,
            'title' => 'Offre 1',
            'description' => 'Détail offre 1',
            'price_amount' => 250000,
            'price_currency' => 'XAF',
            'validity_days' => 14,
            'status' => 'sent',
            'client_token' => bin2hex(random_bytes(16)),
        ]);

        $response = $this->getJson('/api/quote-requests/mine/offers');

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin endpoints
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_all_quote_requests(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $this->makeQuoteRequest();
        $this->makeQuoteRequest();
        $this->makeQuoteRequest();

        $response = $this->getJson('/api/admin/quote-requests');

        $response->assertOk();
    }

    public function test_non_admin_cannot_list_quote_requests(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/quote-requests');

        $response->assertForbidden();
    }

    public function test_admin_can_show_quote_request(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $qr = $this->makeQuoteRequest();

        $response = $this->getJson("/api/admin/quote-requests/{$qr->id}");

        $response->assertOk()->assertJsonPath('data.id', $qr->id);
    }

    public function test_admin_can_update_quote_request_stage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $qr = $this->makeQuoteRequest();

        $response = $this->patchJson("/api/admin/quote-requests/{$qr->id}/stage", [
            'stage_id' => $this->stageQualified->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quote_requests', [
            'id' => $qr->id,
            'current_stage_id' => $this->stageQualified->id,
        ]);
    }

    public function test_admin_can_assign_quote_request_to_themselves(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $qr = $this->makeQuoteRequest();

        $response = $this->patchJson("/api/admin/quote-requests/{$qr->id}/assign", [
            'assigned_admin_id' => $admin->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quote_requests', [
            'id' => $qr->id,
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function test_admin_can_add_note_to_quote_request(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $qr = $this->makeQuoteRequest();

        $response = $this->postJson("/api/admin/quote-requests/{$qr->id}/notes", [
            'note' => 'Premier contact effectué, le client est intéressé.',
        ]);

        $response->assertSuccessful();
    }

    public function test_admin_can_update_outcome(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $qr = $this->makeQuoteRequest();

        $response = $this->patchJson("/api/admin/quote-requests/{$qr->id}/outcome", [
            'outcome' => 'won',
            'outcome_note' => 'Contrat signé pour 6 mois.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quote_requests', [
            'id' => $qr->id,
            'outcome' => 'won',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin: Quote request stages CRUD
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_quote_stages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/quote-request-stages');

        $response->assertOk();
        // 2 stages were created in setUp().
        $this->assertGreaterThanOrEqual(2, count($response->json('data') ?? $response->json()));
    }

    public function test_admin_can_create_custom_stage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/quote-request-stages', [
            'name' => 'Proposition envoyée',
            'slug' => 'proposal_sent',
            'sort_order' => 5,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('quote_request_stages', ['slug' => 'proposal_sent']);
    }

    public function test_admin_cannot_create_stage_with_duplicate_slug(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/quote-request-stages', [
            'name' => 'Doublon',
            'slug' => 'new', // already exists from setUp
            'sort_order' => 99,
        ]);

        $response->assertUnprocessable();
    }

    public function test_admin_can_update_custom_stage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/quote-request-stages/{$this->stageQualified->id}", [
            'name' => 'Qualifié',
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quote_request_stages', [
            'id' => $this->stageQualified->id,
            'name' => 'Qualifié',
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_delete_system_stage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/quote-request-stages/{$this->stageNew->id}");

        // System stages are protected — backend returns 4xx.
        $this->assertTrue($response->status() >= 400 && $response->status() < 500);
        $this->assertDatabaseHas('quote_request_stages', ['id' => $this->stageNew->id]);
    }

    public function test_admin_can_delete_custom_stage(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/quote-request-stages/{$this->stageQualified->id}");

        $response->assertSuccessful();
        $this->assertDatabaseMissing('quote_request_stages', ['id' => $this->stageQualified->id]);
    }

    public function test_admin_can_reorder_stages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/admin/quote-request-stages/reorder', [
            'stages' => [
                ['id' => $this->stageNew->id, 'sort_order' => 10],
                ['id' => $this->stageQualified->id, 'sort_order' => 5],
            ],
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('quote_request_stages', [
            'id' => $this->stageNew->id,
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('quote_request_stages', [
            'id' => $this->stageQualified->id,
            'sort_order' => 5,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin: custom offers
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_custom_offer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $qr = $this->makeQuoteRequest();

        $response = $this->postJson("/api/admin/quote-requests/{$qr->id}/offers", [
            'title' => 'Offre premium 6 mois',
            'description' => 'Accès complet plateforme + accompagnement.',
            'price_amount' => 750000,
            'price_currency' => 'XAF',
            'validity_days' => 14,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('custom_offers', [
            'quote_request_id' => $qr->id,
            'price_amount' => 750000,
        ]);
    }

    public function test_admin_can_list_offers_for_quote_request(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $qr = $this->makeQuoteRequest();
        CustomOffer::create([
            'quote_request_id' => $qr->id,
            'created_by' => $admin->id,
            'title' => 'Offre A',
            'description' => 'Description A',
            'price_amount' => 100000,
            'price_currency' => 'XAF',
            'validity_days' => 14,
            'status' => 'draft',
            'client_token' => bin2hex(random_bytes(16)),
        ]);

        $response = $this->getJson("/api/admin/quote-requests/{$qr->id}/offers");

        $response->assertOk();
    }
}
