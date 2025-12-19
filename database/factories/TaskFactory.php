<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Common event planning tasks.
     */
    protected array $taskTemplates = [
        'Réserver le lieu',
        'Contacter le traiteur',
        'Envoyer les invitations',
        'Choisir le gâteau',
        'Réserver le photographe',
        'Organiser la décoration',
        'Prévoir la musique/DJ',
        'Confirmer les invités',
        'Planifier le menu',
        'Louer le matériel',
        'Organiser le transport',
        'Préparer les cadeaux invités',
        'Finaliser le plan de table',
        'Réserver hébergement invités',
        'Commander les fleurs',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(TaskStatus::cases());
        $isCompleted = $status === TaskStatus::COMPLETED;

        return [
            'event_id' => Event::factory(),
            'assigned_to_user_id' => fake()->optional(0.5)->randomElement([User::factory()]),
            'title' => fake()->randomElement($this->taskTemplates),
            'description' => fake()->optional(0.4)->paragraph(),
            'status' => $status->value,
            'priority' => fake()->randomElement(TaskPriority::cases())->value,
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+3 months'),
            'completed_at' => $isCompleted ? fake()->dateTimeBetween('-1 month', 'now') : null,
        ];
    }

    /**
     * Indicate that the task is todo.
     */
    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::TODO->value,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::IN_PROGRESS->value,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::COMPLETED->value,
            'completed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the task is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TaskPriority::HIGH->value,
        ]);
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::TODO->value,
            'due_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'completed_at' => null,
        ]);
    }
}
