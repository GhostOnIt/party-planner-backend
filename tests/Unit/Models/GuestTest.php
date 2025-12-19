<?php

namespace Tests\Unit\Models;

use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_belongs_to_event(): void
    {
        $event = Event::factory()->create();
        $guest = Guest::factory()->create(['event_id' => $event->id]);

        $this->assertInstanceOf(Event::class, $guest->event);
        $this->assertEquals($event->id, $guest->event->id);
    }

    public function test_guest_has_confirmed(): void
    {
        $guest = Guest::factory()->accepted()->create();

        $this->assertTrue($guest->hasConfirmed());
    }

    public function test_guest_has_declined(): void
    {
        $guest = Guest::factory()->declined()->create();

        $this->assertTrue($guest->hasDeclined());
    }

    public function test_guest_is_pending(): void
    {
        $guest = Guest::factory()->pending()->create();

        $this->assertTrue($guest->isPending());
    }

    public function test_invitation_sent(): void
    {
        $guest = Guest::factory()->invitationSent()->create();

        $this->assertTrue($guest->invitationSent());
    }

    public function test_invitation_not_sent(): void
    {
        $guest = Guest::factory()->create(['invitation_sent_at' => null]);

        $this->assertFalse($guest->invitationSent());
    }

    public function test_guest_checked_in(): void
    {
        $guest = Guest::factory()->checkedIn()->create();

        $this->assertTrue($guest->checked_in);
        $this->assertNotNull($guest->checked_in_at);
    }
}
