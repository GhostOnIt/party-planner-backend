<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = ['create', 'update', 'delete', 'view', 'login', 'update_role', 'toggle_active'];
        $action = fake()->randomElement($actions);

        return [
            'user_id' => User::factory()->state(['role' => UserRole::ADMIN]),
            'actor_type' => ActivityLog::ACTOR_ADMIN,
            'action' => $action,
            'model_type' => null,
            'model_id' => null,
            'description' => fake()->sentence(),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'source' => ActivityLog::SOURCE_API,
            'page_url' => null,
            'session_id' => null,
            'metadata' => null,
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * State for user-related actions.
     */
    public function userAction(): static
    {
        return $this->state(function (array $attributes) {
            $targetUser = User::factory()->create();
            $actions = ['view', 'update', 'update_role', 'delete'];
            $action = fake()->randomElement($actions);

            $descriptions = [
                'view' => "Consultation du profil de {$targetUser->name}",
                'update' => "Modification de l'utilisateur {$targetUser->name}",
                'update_role' => "Changement de rôle de {$targetUser->name}",
                'delete' => "Suppression de l'utilisateur {$targetUser->name}",
            ];

            return [
                'action' => $action,
                'model_type' => User::class,
                'model_id' => $targetUser->id,
                'description' => $descriptions[$action],
                'old_values' => $action === 'update_role' ? ['role' => 'user'] : null,
                'new_values' => $action === 'update_role' ? ['role' => 'admin'] : null,
            ];
        });
    }

    /**
     * State for template-related actions.
     */
    public function templateAction(): static
    {
        return $this->state(function (array $attributes) {
            $template = EventTemplate::factory()->create();
            $actions = ['view', 'create', 'update', 'delete', 'toggle_active'];
            $action = fake()->randomElement($actions);

            $descriptions = [
                'view' => "Consultation du template {$template->name}",
                'create' => "Création du template {$template->name}",
                'update' => "Modification du template {$template->name}",
                'delete' => "Suppression du template {$template->name}",
                'toggle_active' => $template->is_active
                    ? "Activation du template {$template->name}"
                    : "Désactivation du template {$template->name}",
            ];

            return [
                'action' => $action,
                'model_type' => EventTemplate::class,
                'model_id' => $template->id,
                'description' => $descriptions[$action],
                'old_values' => $action === 'toggle_active' ? ['is_active' => !$template->is_active] : null,
                'new_values' => $action === 'toggle_active' ? ['is_active' => $template->is_active] : null,
            ];
        });
    }

    /**
     * State for event-related actions.
     */
    public function eventAction(): static
    {
        return $this->state(function (array $attributes) {
            $event = Event::factory()->create();
            $actions = ['view', 'create', 'update', 'delete'];
            $action = fake()->randomElement($actions);

            $descriptions = [
                'view' => "Consultation de l'événement {$event->title}",
                'create' => "Création de l'événement {$event->title}",
                'update' => "Modification de l'événement {$event->title}",
                'delete' => "Suppression de l'événement {$event->title}",
            ];

            return [
                'action' => $action,
                'model_type' => Event::class,
                'model_id' => $event->id,
                'description' => $descriptions[$action],
            ];
        });
    }

    /**
     * State for login actions.
     */
    public function login(): static
    {
        return $this->state(function (array $attributes) {
            $admin = User::factory()->state(['role' => UserRole::ADMIN])->create();

            return [
                'user_id' => $admin->id,
                'actor_type' => ActivityLog::ACTOR_ADMIN,
                'action' => 'login',
                'model_type' => null,
                'model_id' => null,
                'description' => "Connexion de l'administrateur {$admin->name}",
                'old_values' => null,
                'new_values' => null,
            ];
        });
    }

    /**
     * State for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'actor_type' => $user->isAdmin() ? ActivityLog::ACTOR_ADMIN : ActivityLog::ACTOR_USER,
        ]);
    }

    /**
     * Alias rétro-compatible.
     */
    public function forAdmin(User $admin): static
    {
        return $this->forUser($admin);
    }

    /**
     * State for frontend navigation logs.
     */
    public function navigation(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_type' => ActivityLog::ACTOR_USER,
            'action' => 'page_view',
            'source' => ActivityLog::SOURCE_NAVIGATION,
            'page_url' => fake()->randomElement([
                '/dashboard', '/events', '/events/create', '/profile',
                '/settings', '/invitations', '/notifications', '/subscriptions',
            ]),
            'session_id' => fake()->uuid(),
            'description' => 'Navigation vers ' . fake()->randomElement([
                '/dashboard', '/events', '/events/create', '/profile',
            ]),
        ]);
    }

    /**
     * State for frontend UI interaction logs.
     */
    public function uiInteraction(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_type' => ActivityLog::ACTOR_USER,
            'action' => fake()->randomElement(['click', 'modal_open', 'modal_close', 'filter_applied', 'tab_change']),
            'source' => ActivityLog::SOURCE_UI_INTERACTION,
            'page_url' => fake()->randomElement(['/dashboard', '/events', '/admin/users']),
            'session_id' => fake()->uuid(),
            'metadata' => ['element' => fake()->randomElement(['button-create', 'modal-delete', 'filter-status', 'tab-guests'])],
        ]);
    }

    /**
     * State with a specific timestamp.
     */
    public function createdAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $date,
        ]);
    }
}
