<?php

namespace Database\Factories;

use App\Enums\RsvpStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Guest>
 */
class GuestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rsvpStatus = fake()->randomElement(RsvpStatus::cases());
        $checkedIn = $rsvpStatus === RsvpStatus::ACCEPTED && fake()->boolean(30);

        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'email' => fake()->optional(0.8)->safeEmail(),
            'phone' => fake()->optional(0.6)->phoneNumber(),
            'rsvp_status' => $rsvpStatus->value,
            'checked_in' => $checkedIn,
            'checked_in_at' => $checkedIn ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'invitation_sent_at' => fake()->optional(0.7)->dateTimeBetween('-2 months', 'now'),
            'reminder_sent_at' => fake()->optional(0.3)->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    /**
     * Indicate that the guest has accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'rsvp_status' => RsvpStatus::ACCEPTED->value,
        ]);
    }

    /**
     * Indicate that the guest has declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'rsvp_status' => RsvpStatus::DECLINED->value,
            'checked_in' => false,
            'checked_in_at' => null,
        ]);
    }

    /**
     * Indicate that the guest is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'rsvp_status' => RsvpStatus::PENDING->value,
            'checked_in' => false,
            'checked_in_at' => null,
        ]);
    }

    /**
     * Indicate that the guest has checked in.
     */
    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'rsvp_status' => RsvpStatus::ACCEPTED->value,
            'checked_in' => true,
            'checked_in_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that invitation was sent.
     */
    public function invitationSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'invitation_sent_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
