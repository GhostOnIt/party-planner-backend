<?php

namespace Database\Factories;

use App\Enums\CollaboratorRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Collaborator>
 */
class CollaboratorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invitedAt = fake()->dateTimeBetween('-1 month', 'now');
        $accepted = fake()->boolean(80);

        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement([CollaboratorRole::EDITOR, CollaboratorRole::VIEWER])->value,
            'invited_at' => $invitedAt,
            'accepted_at' => $accepted ? fake()->dateTimeBetween($invitedAt, 'now') : null,
        ];
    }

    /**
     * Indicate that the collaborator is an owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CollaboratorRole::OWNER->value,
            'accepted_at' => $attributes['invited_at'] ?? now(),
        ]);
    }

    /**
     * Indicate that the collaborator is an editor.
     */
    public function editor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CollaboratorRole::EDITOR->value,
        ]);
    }

    /**
     * Indicate that the collaborator is a viewer.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CollaboratorRole::VIEWER->value,
        ]);
    }

    /**
     * Indicate that the invitation was accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => fake()->dateTimeBetween($attributes['invited_at'] ?? '-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the invitation is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => null,
        ]);
    }
}
