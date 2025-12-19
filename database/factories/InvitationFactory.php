<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sentAt = fake()->optional(0.8)->dateTimeBetween('-1 month', 'now');
        $openedAt = $sentAt ? fake()->optional(0.7)->dateTimeBetween($sentAt, 'now') : null;
        $respondedAt = $openedAt ? fake()->optional(0.5)->dateTimeBetween($openedAt, 'now') : null;

        return [
            'event_id' => Event::factory(),
            'guest_id' => Guest::factory(),
            'token' => Str::random(32),
            'sent_at' => $sentAt,
            'opened_at' => $openedAt,
            'responded_at' => $respondedAt,
            'template_id' => fake()->optional(0.5)->randomElement(['classic', 'modern', 'elegant', 'fun']),
            'custom_message' => fake()->optional(0.3)->paragraph(),
        ];
    }

    /**
     * Indicate that the invitation was sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the invitation was opened.
     */
    public function opened(): static
    {
        return $this->state(function (array $attributes) {
            $sentAt = $attributes['sent_at'] ?? fake()->dateTimeBetween('-1 month', 'now');
            return [
                'sent_at' => $sentAt,
                'opened_at' => fake()->dateTimeBetween($sentAt, 'now'),
            ];
        });
    }

    /**
     * Indicate that the invitation was responded to.
     */
    public function responded(): static
    {
        return $this->state(function (array $attributes) {
            $sentAt = $attributes['sent_at'] ?? fake()->dateTimeBetween('-1 month', '-1 week');
            $openedAt = $attributes['opened_at'] ?? fake()->dateTimeBetween($sentAt, '-1 day');
            return [
                'sent_at' => $sentAt,
                'opened_at' => $openedAt,
                'responded_at' => fake()->dateTimeBetween($openedAt, 'now'),
            ];
        });
    }
}
