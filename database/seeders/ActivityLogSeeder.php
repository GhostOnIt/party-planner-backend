<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    use WithoutModelEvents;

    protected $faker;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->faker = Factory::create('fr_FR');

        $admins = User::where('role', UserRole::ADMIN)->get();
        $users = User::where('role', UserRole::USER)->get();
        $events = Event::all();
        $templates = EventTemplate::all();

        // Logs pour les admins
        if ($admins->isNotEmpty()) {
            $this->command->info("Création des logs d'activité pour {$admins->count()} admin(s)...");
            foreach ($admins as $admin) {
                $this->command->info("  Admin: {$admin->name}");
                $this->createAdminLogs($admin, $users, $events, $templates);
            }
        }

        // Logs pour les utilisateurs
        if ($users->isNotEmpty()) {
            $this->command->info("Création des logs d'activité pour {$users->count()} utilisateur(s)...");
            foreach ($users as $user) {
                $this->createUserLogs($user, $events);
            }
        }

        $this->command->info('Logs d\'activité créés avec succès !');
    }

    /**
     * Create admin activity logs.
     */
    protected function createAdminLogs(User $admin, $users, $events, $templates): void
    {
        $logCount = $this->faker->numberBetween(50, 100);
        $logs = [];

        for ($i = 0; $i < $logCount; $i++) {
            $log = $this->createRandomAdminLog($admin, $users, $events, $templates);
            if ($log) {
                $logs[] = $log;
            }
        }

        if (!empty($logs)) {
            ActivityLog::insert($logs);
        }
    }

    /**
     * Create user activity logs (API + navigation + UI).
     */
    protected function createUserLogs(User $user, $events): void
    {
        $logCount = $this->faker->numberBetween(30, 80);
        $logs = [];

        for ($i = 0; $i < $logCount; $i++) {
            $log = $this->createRandomUserLog($user, $events);
            if ($log) {
                $logs[] = $log;
            }
        }

        if (!empty($logs)) {
            ActivityLog::insert($logs);
        }
    }

    /**
     * Create a random admin log entry.
     */
    protected function createRandomAdminLog(User $admin, $users, $events, $templates): ?array
    {
        $weights = [
            'login' => 25,
            'user_view' => 20,
            'user_update' => 10,
            'user_delete' => 5,
            'event_view' => 15,
            'template_create' => 8,
            'template_update' => 10,
            'template_toggle' => 7,
        ];

        $type = $this->weightedRandom($weights);
        $createdAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $base = $this->baseLogData($admin, ActivityLog::ACTOR_ADMIN, ActivityLog::SOURCE_API, $createdAt);

        return match ($type) {
            'login' => array_merge($base, [
                'action' => 'login',
                'description' => "Connexion de l'administrateur {$admin->name}",
            ]),
            'user_view' => $users->isEmpty() ? null : array_merge($base, [
                'action' => 'view',
                'model_type' => User::class,
                'model_id' => $users->random()->id,
                'description' => "Consultation du profil de {$users->random()->name}",
            ]),
            'user_update' => $users->isEmpty() ? null : $this->userUpdateLog($base, $users->random()),
            'user_delete' => $users->isEmpty() ? null : $this->userDeleteLog($base, $users->random()),
            'event_view' => $events->isEmpty() ? null : array_merge($base, [
                'action' => 'view',
                'model_type' => Event::class,
                'model_id' => $events->random()->id,
                'description' => "Consultation de l'événement {$events->random()->title}",
            ]),
            'template_create' => $templates->isEmpty() ? null : $this->templateCreateLog($base, $templates->random()),
            'template_update' => $templates->isEmpty() ? null : $this->templateUpdateLog($base, $templates->random()),
            'template_toggle' => $templates->isEmpty() ? null : $this->templateToggleLog($base, $templates->random()),
            default => null,
        };
    }

    /**
     * Create a random user log entry (API + navigation + UI).
     */
    protected function createRandomUserLog(User $user, $events): ?array
    {
        $weights = [
            'login' => 15,
            'navigation' => 30,
            'ui_interaction' => 20,
            'event_create' => 10,
            'event_update' => 10,
            'profile_update' => 8,
            'event_view' => 7,
        ];

        $type = $this->weightedRandom($weights);
        $createdAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $sessionId = $this->faker->uuid();

        return match ($type) {
            'login' => array_merge(
                $this->baseLogData($user, ActivityLog::ACTOR_USER, ActivityLog::SOURCE_API, $createdAt),
                [
                    'action' => 'login',
                    'description' => "Connexion de l'utilisateur {$user->name}",
                ]
            ),
            'navigation' => array_merge(
                $this->baseLogData($user, ActivityLog::ACTOR_USER, ActivityLog::SOURCE_NAVIGATION, $createdAt),
                [
                    'action' => 'page_view',
                    'page_url' => $this->faker->randomElement([
                        '/dashboard', '/events', '/events/create', '/profile',
                        '/settings', '/invitations', '/notifications', '/subscriptions',
                    ]),
                    'session_id' => $sessionId,
                    'description' => 'Navigation vers ' . $this->faker->randomElement([
                        '/dashboard', '/events', '/events/create', '/profile',
                    ]),
                ]
            ),
            'ui_interaction' => array_merge(
                $this->baseLogData($user, ActivityLog::ACTOR_USER, ActivityLog::SOURCE_UI_INTERACTION, $createdAt),
                [
                    'action' => $this->faker->randomElement(['click', 'modal_open', 'filter_applied', 'tab_change']),
                    'page_url' => $this->faker->randomElement(['/events', '/dashboard', '/events/1']),
                    'session_id' => $sessionId,
                    'description' => 'Interaction UI sur ' . $this->faker->randomElement([
                        'bouton créer', 'modal suppression', 'filtre statut', 'onglet invités',
                    ]),
                    'metadata' => json_encode(['element' => $this->faker->randomElement([
                        'btn-create-event', 'modal-delete', 'filter-status', 'tab-guests',
                    ])]),
                ]
            ),
            'event_create' => $events->isEmpty() ? null : array_merge(
                $this->baseLogData($user, ActivityLog::ACTOR_USER, ActivityLog::SOURCE_API, $createdAt),
                [
                    'action' => 'create',
                    'model_type' => Event::class,
                    'model_id' => $events->random()->id,
                    'description' => "Création d'un événement par {$user->name}",
                ]
            ),
            'event_update' => $events->isEmpty() ? null : array_merge(
                $this->baseLogData($user, ActivityLog::ACTOR_USER, ActivityLog::SOURCE_API, $createdAt),
                [
                    'action' => 'update',
                    'model_type' => Event::class,
                    'model_id' => $events->random()->id,
                    'description' => "Modification d'un événement par {$user->name}",
                ]
            ),
            'event_view' => $events->isEmpty() ? null : array_merge(
                $this->baseLogData($user, ActivityLog::ACTOR_USER, ActivityLog::SOURCE_API, $createdAt),
                [
                    'action' => 'view',
                    'model_type' => Event::class,
                    'model_id' => $events->random()->id,
                    'description' => "Consultation d'un événement par {$user->name}",
                ]
            ),
            'profile_update' => array_merge(
                $this->baseLogData($user, ActivityLog::ACTOR_USER, ActivityLog::SOURCE_API, $createdAt),
                [
                    'action' => 'update',
                    'model_type' => User::class,
                    'model_id' => $user->id,
                    'description' => "Modification du profil par {$user->name}",
                ]
            ),
            default => null,
        };
    }

    /**
     * Base log data.
     */
    protected function baseLogData(User $user, string $actorType, string $source, \DateTimeInterface $createdAt): array
    {
        return [
            'user_id' => $user->id,
            'actor_type' => $actorType,
            'action' => '',
            'model_type' => null,
            'model_id' => null,
            'description' => '',
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'source' => $source,
            'page_url' => null,
            'session_id' => null,
            'metadata' => null,
            's3_key' => null,
            's3_archived_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    protected function userUpdateLog(array $base, User $targetUser): array
    {
        return array_merge($base, [
            'action' => 'update_role',
            'model_type' => User::class,
            'model_id' => $targetUser->id,
            'description' => "Changement de rôle de {$targetUser->name}",
            'old_values' => json_encode(['role' => 'user']),
            'new_values' => json_encode(['role' => $this->faker->randomElement(['user', 'admin'])]),
        ]);
    }

    protected function userDeleteLog(array $base, User $targetUser): array
    {
        return array_merge($base, [
            'action' => 'delete',
            'model_type' => User::class,
            'model_id' => $targetUser->id,
            'description' => "Suppression de l'utilisateur {$targetUser->name}",
            'old_values' => json_encode(['id' => $targetUser->id, 'name' => $targetUser->name, 'email' => $targetUser->email]),
        ]);
    }

    protected function templateCreateLog(array $base, EventTemplate $template): array
    {
        return array_merge($base, [
            'action' => 'create',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
            'description' => "Création du template {$template->name}",
            'new_values' => json_encode(['name' => $template->name, 'event_type' => $template->event_type]),
        ]);
    }

    protected function templateUpdateLog(array $base, EventTemplate $template): array
    {
        return array_merge($base, [
            'action' => 'update',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
            'description' => "Modification du template {$template->name}",
            'old_values' => json_encode(['name' => 'Ancien nom']),
            'new_values' => json_encode(['name' => $template->name]),
        ]);
    }

    protected function templateToggleLog(array $base, EventTemplate $template): array
    {
        $newStatus = $this->faker->boolean();
        return array_merge($base, [
            'action' => 'toggle_active',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
            'description' => $newStatus
                ? "Activation du template {$template->name}"
                : "Désactivation du template {$template->name}",
            'old_values' => json_encode(['is_active' => !$newStatus]),
            'new_values' => json_encode(['is_active' => $newStatus]),
        ]);
    }

    /**
     * Weighted random selection.
     */
    protected function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $random = $this->faker->numberBetween(1, $total);
        $cumulative = 0;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
