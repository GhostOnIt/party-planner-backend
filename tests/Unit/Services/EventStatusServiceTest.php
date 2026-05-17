<?php

namespace Tests\Unit\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\EventStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EventStatusService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventStatusService();
        $this->user = User::factory()->create();
    }

    public function test_can_transition_to_cancelled_at_any_time(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->addMonths(2),
            'time' => '20:00',
        ]);

        $this->assertTrue($this->service->canTransitionTo($event, EventStatus::CANCELLED));
    }

    public function test_cannot_transition_to_ongoing_more_than_24h_before(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->addDays(3),
            'time' => '20:00',
        ]);

        $this->assertFalse($this->service->canTransitionTo($event, EventStatus::ONGOING));
    }

    public function test_can_transition_to_ongoing_within_24h_before(): void
    {
        Carbon::setTestNow('2026-05-15 10:00'); // 10 hours before event start

        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => Carbon::parse('2026-05-15'),
            'time' => '20:00',
        ]);

        $this->assertTrue($this->service->canTransitionTo($event, EventStatus::ONGOING));

        Carbon::setTestNow();
    }

    public function test_cannot_transition_to_completed_before_event_start(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->addDay(),
            'time' => '20:00',
        ]);

        $this->assertFalse($this->service->canTransitionTo($event, EventStatus::COMPLETED));
    }

    public function test_can_transition_to_completed_after_event_start(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->subHour(),
            'time' => '10:00',
        ]);

        $this->assertTrue($this->service->canTransitionTo($event, EventStatus::COMPLETED));
    }

    public function test_can_transition_to_upcoming_before_event_start(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->addWeek(),
            'time' => '20:00',
        ]);

        $this->assertTrue($this->service->canTransitionTo($event, EventStatus::UPCOMING));
    }

    public function test_cannot_transition_to_upcoming_after_event_start(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->subDay(),
            'time' => '10:00',
        ]);

        $this->assertFalse($this->service->canTransitionTo($event, EventStatus::UPCOMING));
    }

    public function test_error_message_for_ongoing_mentions_24h_window(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->addWeek(),
            'time' => '20:00',
        ]);

        $message = $this->service->getTransitionErrorMessage($event, EventStatus::ONGOING);

        $this->assertStringContainsString('24h avant', $message);
    }

    public function test_error_message_for_completed_mentions_event_time(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->addWeek(),
            'time' => '20:00',
        ]);

        $message = $this->service->getTransitionErrorMessage($event, EventStatus::COMPLETED);

        $this->assertStringContainsString('après l\'heure prévue', $message);
    }

    public function test_error_message_for_cancelled_is_empty(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => now()->addWeek(),
            'time' => '20:00',
        ]);

        $this->assertSame('', $this->service->getTransitionErrorMessage($event, EventStatus::CANCELLED));
    }

    public function test_get_event_start_combines_date_and_time(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => '2026-06-15',
            'time' => '14:30',
        ]);

        $start = $this->service->getEventStart($event);

        $this->assertSame('2026-06-15 14:30:00', $start->format('Y-m-d H:i:s'));
    }

    public function test_get_event_start_defaults_to_midnight_when_no_time(): void
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'date' => '2026-06-15',
            'time' => null,
        ]);

        $start = $this->service->getEventStart($event);

        $this->assertSame('00:00:00', $start->format('H:i:s'));
    }

    public function test_get_event_start_returns_far_future_when_no_date(): void
    {
        $event = Event::factory()->make([
            'user_id' => $this->user->id,
            'date' => null,
            'time' => null,
        ]);

        $start = $this->service->getEventStart($event);

        $this->assertTrue($start->isAfter(now()->addMonths(11)));
    }
}
