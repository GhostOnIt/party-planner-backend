<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Notification templates by type.
     */
    protected array $templates = [
        'task_reminder' => [
            'title' => 'Rappel de tâche',
            'messages' => [
                'La tâche "%s" arrive à échéance demain.',
                'N\'oubliez pas de compléter "%s".',
                'La tâche "%s" est en retard.',
            ],
        ],
        'guest_reminder' => [
            'title' => 'Rappel invités',
            'messages' => [
                '%d invités n\'ont pas encore répondu.',
                'Relancez vos invités en attente.',
                'Nouveau RSVP reçu !',
            ],
        ],
        'budget_alert' => [
            'title' => 'Alerte budget',
            'messages' => [
                'Vous avez dépassé le budget estimé.',
                'Attention : dépenses élevées dans la catégorie %s.',
                'Votre budget est presque atteint.',
            ],
        ],
        'event_reminder' => [
            'title' => 'Rappel événement',
            'messages' => [
                'Votre événement est dans %d jours.',
                'J-7 avant le grand jour !',
                'Dernière ligne droite pour votre événement.',
            ],
        ],
        'collaboration_invite' => [
            'title' => 'Invitation collaboration',
            'messages' => [
                '%s vous a invité à collaborer.',
                'Nouvelle invitation à rejoindre un événement.',
                'Vous avez été ajouté comme collaborateur.',
            ],
        ],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(NotificationType::cases());
        $template = $this->templates[$type->value];

        return [
            'user_id' => User::factory(),
            'event_id' => fake()->optional(0.8)->randomElement([Event::factory()]),
            'type' => $type->value,
            'title' => $template['title'],
            'message' => fake()->randomElement($template['messages']),
            'read_at' => fake()->optional(0.4)->dateTimeBetween('-1 week', 'now'),
            'sent_via' => fake()->randomElement(['email', 'push']),
        ];
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the notification is of a specific type.
     */
    public function ofType(NotificationType $type): static
    {
        $template = $this->templates[$type->value];

        return $this->state(fn (array $attributes) => [
            'type' => $type->value,
            'title' => $template['title'],
            'message' => fake()->randomElement($template['messages']),
        ]);
    }
}
