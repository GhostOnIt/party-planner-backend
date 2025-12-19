<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\BudgetItem;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Task;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportControllerTest extends TestCase
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
    | Export Guests CSV Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_guests_to_csv(): void
    {
        Sanctum::actingAs($this->user);

        Guest::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/guests/csv");

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_user_cannot_export_guests_from_other_user_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->get("/api/events/{$otherEvent->id}/exports/guests/csv");

        $response->assertForbidden();
    }

    public function test_collaborator_can_export_guests(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'role' => 'viewer',
        ]);

        Guest::factory()->count(3)->create(['event_id' => $event->id]);

        $response = $this->get("/api/events/{$event->id}/exports/guests/csv");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Export Guests PDF Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_guests_to_pdf(): void
    {
        Sanctum::actingAs($this->user);

        Guest::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/guests/pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | Export Guests XLSX Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_guests_to_xlsx(): void
    {
        Sanctum::actingAs($this->user);

        Guest::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/guests/xlsx");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Export Budget CSV Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_budget_to_csv(): void
    {
        Sanctum::actingAs($this->user);

        BudgetItem::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/budget/csv");

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_user_cannot_export_budget_from_other_user_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->get("/api/events/{$otherEvent->id}/exports/budget/csv");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Export Budget PDF Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_budget_to_pdf(): void
    {
        Sanctum::actingAs($this->user);

        BudgetItem::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/budget/pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | Export Budget XLSX Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_budget_to_xlsx(): void
    {
        Sanctum::actingAs($this->user);

        BudgetItem::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/budget/xlsx");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Export Tasks CSV Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_tasks_to_csv(): void
    {
        Sanctum::actingAs($this->user);

        Task::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/tasks/csv");

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_user_cannot_export_tasks_from_other_user_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->get("/api/events/{$otherEvent->id}/exports/tasks/csv");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Export Tasks XLSX Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_tasks_to_xlsx(): void
    {
        Sanctum::actingAs($this->user);

        Task::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/tasks/xlsx");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Export Full Report Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_export_full_event_report(): void
    {
        Sanctum::actingAs($this->user);

        Guest::factory()->count(3)->create(['event_id' => $this->event->id]);
        Task::factory()->count(3)->create(['event_id' => $this->event->id]);
        BudgetItem::factory()->count(3)->create(['event_id' => $this->event->id]);

        $response = $this->get("/api/events/{$this->event->id}/exports/report/pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_cannot_export_report_from_other_user_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->get("/api/events/{$otherEvent->id}/exports/report/pdf");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_export_any_event_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $event = Event::factory()->create();
        Guest::factory()->count(3)->create(['event_id' => $event->id]);

        $response = $this->get("/api/events/{$event->id}/exports/guests/csv");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Empty Data Tests
    |--------------------------------------------------------------------------
    */

    public function test_export_with_no_guests_returns_empty_csv(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/events/{$this->event->id}/exports/guests/csv");

        $response->assertOk();
    }

    public function test_export_with_no_tasks_returns_empty_csv(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/events/{$this->event->id}/exports/tasks/csv");

        $response->assertOk();
    }

    public function test_export_with_no_budget_items_returns_empty_csv(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/events/{$this->event->id}/exports/budget/csv");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_export_guests(): void
    {
        $response = $this->get("/api/events/{$this->event->id}/exports/guests/csv");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_export_budget(): void
    {
        $response = $this->get("/api/events/{$this->event->id}/exports/budget/csv");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_export_tasks(): void
    {
        $response = $this->get("/api/events/{$this->event->id}/exports/tasks/csv");

        $response->assertUnauthorized();
    }
}
