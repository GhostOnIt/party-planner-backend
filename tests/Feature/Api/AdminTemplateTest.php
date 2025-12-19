<?php

namespace Tests\Feature\Api;

use App\Enums\EventType;
use App\Enums\UserRole;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->user = User::factory()->create(['role' => UserRole::USER]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Template List Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_all_templates(): void
    {
        Sanctum::actingAs($this->admin);

        EventTemplate::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/templates');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'event_type', 'is_active'],
                ],
            ]);
    }

    public function test_admin_can_list_inactive_templates(): void
    {
        Sanctum::actingAs($this->admin);

        EventTemplate::factory()->create(['is_active' => true]);
        EventTemplate::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/admin/templates?active=false');

        $response->assertOk();
        foreach ($response->json('data') as $template) {
            $this->assertFalse($template['is_active']);
        }
    }

    public function test_admin_can_filter_templates_by_type(): void
    {
        Sanctum::actingAs($this->admin);

        EventTemplate::factory()->create(['event_type' => EventType::MARIAGE]);
        EventTemplate::factory()->create(['event_type' => EventType::ANNIVERSAIRE]);

        $response = $this->getJson('/api/admin/templates?type=mariage');

        $response->assertOk();
        foreach ($response->json('data') as $template) {
            $this->assertEquals('mariage', $template['event_type']);
        }
    }

    public function test_admin_can_search_templates(): void
    {
        Sanctum::actingAs($this->admin);

        EventTemplate::factory()->create(['name' => 'Mariage Classique']);
        EventTemplate::factory()->create(['name' => 'Anniversaire Enfant']);

        $response = $this->getJson('/api/admin/templates?search=Classique');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_regular_user_cannot_list_admin_templates(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/admin/templates');

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Template Create Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_template(): void
    {
        Sanctum::actingAs($this->admin);

        $templateData = [
            'event_type' => 'mariage',
            'name' => 'Mariage Champêtre',
            'description' => 'Template pour un mariage champêtre',
            'default_tasks' => [
                ['title' => 'Réserver la salle', 'priority' => 'high'],
                ['title' => 'Choisir le traiteur', 'priority' => 'medium'],
            ],
            'default_budget_categories' => [
                ['name' => 'Salle', 'category' => 'location', 'estimated_cost' => 1500000],
                ['name' => 'Traiteur', 'category' => 'catering', 'estimated_cost' => 2000000],
            ],
            'suggested_themes' => ['Champêtre', 'Bohème', 'Rustique'],
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/templates', $templateData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Mariage Champêtre']);

        $this->assertDatabaseHas('event_templates', [
            'name' => 'Mariage Champêtre',
            'event_type' => 'mariage',
        ]);
    }

    public function test_create_template_requires_name(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/templates', [
            'event_type' => 'mariage',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_template_requires_event_type(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/templates', [
            'name' => 'Test Template',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event_type']);
    }

    public function test_create_template_validates_event_type(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/templates', [
            'name' => 'Test Template',
            'event_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event_type']);
    }

    public function test_regular_user_cannot_create_template(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/admin/templates', [
            'event_type' => 'mariage',
            'name' => 'Test Template',
        ]);

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Template Update Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_template(): void
    {
        Sanctum::actingAs($this->admin);

        $template = EventTemplate::factory()->create([
            'name' => 'Old Name',
            'event_type' => EventType::MARIAGE,
        ]);

        $response = $this->putJson("/api/admin/templates/{$template->id}", [
            'event_type' => 'mariage',
            'name' => 'New Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('event_templates', [
            'id' => $template->id,
            'name' => 'New Name',
        ]);
    }

    public function test_regular_user_cannot_update_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = EventTemplate::factory()->create();

        $response = $this->putJson("/api/admin/templates/{$template->id}", [
            'event_type' => 'mariage',
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Template Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_template(): void
    {
        Sanctum::actingAs($this->admin);

        $template = EventTemplate::factory()->create();

        $response = $this->deleteJson("/api/admin/templates/{$template->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Template supprimé.']);

        $this->assertDatabaseMissing('event_templates', ['id' => $template->id]);
    }

    public function test_regular_user_cannot_delete_template(): void
    {
        Sanctum::actingAs($this->user);

        $template = EventTemplate::factory()->create();

        $response = $this->deleteJson("/api/admin/templates/{$template->id}");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Template Toggle Active Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_toggle_template_active_status(): void
    {
        Sanctum::actingAs($this->admin);

        $template = EventTemplate::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/admin/templates/{$template->id}/toggle-active");

        $response->assertOk()
            ->assertJsonFragment(['is_active' => false]);

        $this->assertDatabaseHas('event_templates', [
            'id' => $template->id,
            'is_active' => false,
        ]);

        // Toggle back
        $response = $this->postJson("/api/admin/templates/{$template->id}/toggle-active");

        $response->assertOk()
            ->assertJsonFragment(['is_active' => true]);
    }

    public function test_regular_user_cannot_toggle_template_active(): void
    {
        Sanctum::actingAs($this->user);

        $template = EventTemplate::factory()->create();

        $response = $this->postJson("/api/admin/templates/{$template->id}/toggle-active");

        $response->assertForbidden();
    }
}
