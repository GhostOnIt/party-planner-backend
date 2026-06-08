<?php

namespace Tests\Feature\Api;

use App\Enums\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetItemPayment;
use App\Models\BudgetPaymentAttachment;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use App\Services\S3Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Mockery;
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

    public function test_user_can_create_partial_budget_payment(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->unpaid()->create([
            'event_id' => $this->event->id,
            'actual_cost' => 500000,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items/{$item->id}/payments", [
            'amount' => 200000,
            'payment_date' => '2026-06-08',
            'method' => 'cash',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['method' => 'cash']);

        $item->refresh();

        $this->assertFalse($item->paid);
        $this->assertSame('partially_paid', $item->payment_status);
        $this->assertEquals(200000, $item->total_paid);
        $this->assertEquals(300000, $item->remaining_amount);
    }

    public function test_mark_paid_creates_payment_for_remaining_amount(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->unpaid()->create([
            'event_id' => $this->event->id,
            'actual_cost' => 500000,
        ]);

        BudgetItemPayment::factory()->forItem($item)->create(['amount' => 200000]);

        $response = $this->postJson("/api/events/{$this->event->id}/budget/items/{$item->id}/mark-paid", [
            'payment_date' => '2026-06-08',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['paid' => true]);

        $item->refresh();

        $this->assertEquals(500000, $item->total_paid);
        $this->assertSame('paid', $item->payment_status);
    }

    public function test_user_can_upload_budget_payment_attachment(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->unpaid()->create(['event_id' => $this->event->id]);
        $payment = BudgetItemPayment::factory()->forItem($item)->create(['amount' => 100000]);

        $this->mockS3Service();

        $response = $this->postJson(
            "/api/events/{$this->event->id}/budget/items/{$item->id}/payments/{$payment->id}/attachments",
            ['file' => UploadedFile::fake()->create('receipt.pdf', 32, 'application/pdf')]
        );

        $response->assertCreated()
            ->assertJsonFragment(['original_name' => 'receipt.pdf']);

        $this->assertDatabaseHas('budget_payment_attachments', [
            'budget_item_payment_id' => $payment->id,
            'budget_item_id' => $item->id,
            'event_id' => $this->event->id,
        ]);
    }

    public function test_user_can_get_budget_payment_attachment_preview_url(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->unpaid()->create(['event_id' => $this->event->id]);
        $payment = BudgetItemPayment::factory()->forItem($item)->create(['amount' => 100000]);
        $attachment = BudgetPaymentAttachment::factory()->forPayment($payment)->create();

        $this->mockS3Service(signedUrl: 'https://signed.example.test/receipt.pdf');

        $response = $this->getJson(
            "/api/events/{$this->event->id}/budget/items/{$item->id}/payments/{$payment->id}/attachments/{$attachment->id}/signed-url"
        );

        $response->assertOk()
            ->assertJsonFragment(['url' => 'https://signed.example.test/receipt.pdf']);
    }

    public function test_budget_payment_attachment_rejects_invalid_file_type(): void
    {
        Sanctum::actingAs($this->user);

        $item = BudgetItem::factory()->unpaid()->create(['event_id' => $this->event->id]);
        $payment = BudgetItemPayment::factory()->forItem($item)->create(['amount' => 100000]);

        $response = $this->postJson(
            "/api/events/{$this->event->id}/budget/items/{$item->id}/payments/{$payment->id}/attachments",
            ['file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    protected function mockS3Service(string $signedUrl = 'https://signed.example.test/receipt.jpg'): void
    {
        $mock = Mockery::mock(S3Service::class);
        $mock->shouldReceive('uploadBudgetPaymentAttachment')
            ->andReturn([
                'success' => true,
                'path' => 'events/test/budget/test/payments/test/receipt.jpg',
                'url' => 'https://example.test/receipt.jpg',
                'storage' => 's3',
            ]);
        $mock->shouldReceive('getSignedUrl')->andReturn($signedUrl);
        $mock->shouldReceive('delete')->andReturn(true);

        $this->app->instance(S3Service::class, $mock);
    }
}
