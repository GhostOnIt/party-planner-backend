<?php

namespace Tests\Feature\Api;

use App\Enums\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['user_id' => $this->user->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Index Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_budget_items(): void
    {
        Sanctum::actingAs($this->user);

        BudgetItem::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/budget");

        $response->assertOk()
            ->assertJsonStructure(['items', 'stats', 'by_category']);
    }

    public function test_user_cannot_list_budget_for_other_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->getJson("/api/events/{$otherEvent->id}/budget");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_get_budget_statistics(): void
    {
        Sanctum::actingAs($this->user);

        BudgetItem::factory()->count(3)->create([
            'event_id' => $this->event->id,
            'estimated_cost' => 100000,
            'actual_cost' => 110000,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/budget/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'by_category',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_create_budget_item(): void
    {
        Sanctum::actingAs($this->user);

        $itemData = [
            'category' => BudgetCategory::CATERING->value,
            'name' => 'Traiteur',
            'estimated_cost' => 500000,
            'notes' => 'Menu complet avec boissons',
        ];

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items", $itemData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Traiteur']);

        $this->assertDatabaseHas('budget_items', [
            'event_id' => $this->event->id,
            'name' => 'Traiteur',
            'category' => BudgetCategory::CATERING->value,
        ]);
    }

    public function test_create_budget_item_requires_category(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items", [
            'name' => 'Test Item',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_create_budget_item_requires_name(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items", [
            'category' => BudgetCategory::OTHER->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_budget_item_validates_cost_is_positive(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items", [
            'category' => BudgetCategory::OTHER->value,
            'name' => 'Test Item',
            'estimated_cost' => -1000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['estimated_cost']);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_view_budget_item(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/budget/items/{$item->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $item->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_update_budget_item(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/events/{$this->event->id}/budget/items/{$item->id}", [
            'category' => $item->category,
            'name' => 'Updated Name',
            'actual_cost' => 150000,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_editor_can_update_budget_item(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
        ]);

        $item = BudgetItem::factory()->create(['event_id' => $this->event->id]);

        $response = $this->putJson("/api/events/{$this->event->id}/budget/items/{$item->id}", [
            'category' => $item->category,
            'name' => 'Editor Update',
        ]);

        $response->assertOk();
    }

    public function test_viewer_cannot_update_budget_item(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $item = BudgetItem::factory()->create(['event_id' => $this->event->id]);

        $response = $this->putJson("/api/events/{$this->event->id}/budget/items/{$item->id}", [
            'category' => $item->category,
            'name' => 'Should Fail',
        ]);

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_delete_budget_item(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->create(['event_id' => $this->event->id]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/budget/items/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('budget_items', ['id' => $item->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Paid/Unpaid Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_mark_item_as_paid(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->unpaid()->create([
            'event_id' => $this->event->id,
            'actual_cost' => 100000,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items/{$item->id}/mark-paid");

        $response->assertOk()
            ->assertJsonFragment(['paid' => true]);

        $this->assertDatabaseHas('budget_items', [
            'id' => $item->id,
            'paid' => true,
        ]);
    }

    public function test_user_can_mark_item_as_unpaid(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->paid()->create(['event_id' => $this->event->id]);

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items/{$item->id}/mark-unpaid");

        $response->assertOk()
            ->assertJsonFragment(['paid' => false]);
    }
}
