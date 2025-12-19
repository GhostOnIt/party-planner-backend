<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminActivityLogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = User::where('role', UserRole::ADMIN)->get();

        if ($admins->isEmpty()) {
            $this->command->warn('No admin users found. Skipping admin activity log seeding.');
            return;
        }

        $this->command->info("Creating admin activity logs for {$admins->count()} admin(s)...");

        // Get some existing data to reference
        $users = User::where('role', UserRole::USER)->get();
        $events = Event::all();
        $templates = EventTemplate::all();

        foreach ($admins as $admin) {
            $this->command->info("  Processing admin: {$admin->name}");
            $this->createLogsForAdmin($admin, $users, $events, $templates);
        }

        $this->command->info('Admin activity logs created successfully!');
    }

    /**
     * Create activity logs for a single admin.
     */
    protected function createLogsForAdmin(
        User $admin,
        $users,
        $events,
        $templates
    ): void {
        // Create 50-100 logs over the last 30 days
        $logCount = fake()->numberBetween(50, 100);
        $logs = [];

        for ($i = 0; $i < $logCount; $i++) {
            $log = $this->createRandomLog($admin, $users, $events, $templates);
            if ($log) {
                $logs[] = $log;
            }
        }

        // Batch insert for performance
        AdminActivityLog::insert($logs);
    }

    /**
     * Create a random log entry.
     */
    protected function createRandomLog(
        User $admin,
        $users,
        $events,
        $templates
    ): ?array {
        $logTypes = ['login', 'user_view', 'user_update', 'user_delete', 'event_view', 'template_create', 'template_update', 'template_toggle'];

        // Weight login more heavily
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
        $createdAt = fake()->dateTimeBetween('-30 days', 'now');

        return match ($type) {
            'login' => $this->createLoginLog($admin, $createdAt),
            'user_view' => $this->createUserViewLog($admin, $users, $createdAt),
            'user_update' => $this->createUserUpdateLog($admin, $users, $createdAt),
            'user_delete' => $this->createUserDeleteLog($admin, $users, $createdAt),
            'event_view' => $this->createEventViewLog($admin, $events, $createdAt),
            'template_create' => $this->createTemplateCreateLog($admin, $templates, $createdAt),
            'template_update' => $this->createTemplateUpdateLog($admin, $templates, $createdAt),
            'template_toggle' => $this->createTemplateToggleLog($admin, $templates, $createdAt),
            default => null,
        };
    }

    /**
     * Create a login log.
     */
    protected function createLoginLog(User $admin, \DateTimeInterface $createdAt): array
    {
        return [
            'admin_id' => $admin->id,
            'action' => 'login',
            'model_type' => null,
            'model_id' => null,
            'description' => "Connexion de l'administrateur {$admin->name}",
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Create a user view log.
     */
    protected function createUserViewLog(User $admin, $users, \DateTimeInterface $createdAt): ?array
    {
        if ($users->isEmpty()) return null;

        $targetUser = $users->random();

        return [
            'admin_id' => $admin->id,
            'action' => 'view',
            'model_type' => User::class,
            'model_id' => $targetUser->id,
            'description' => "Consultation du profil de {$targetUser->name}",
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Create a user update log.
     */
    protected function createUserUpdateLog(User $admin, $users, \DateTimeInterface $createdAt): ?array
    {
        if ($users->isEmpty()) return null;

        $targetUser = $users->random();

        return [
            'admin_id' => $admin->id,
            'action' => 'update_role',
            'model_type' => User::class,
            'model_id' => $targetUser->id,
            'description' => "Changement de rôle de {$targetUser->name}",
            'old_values' => json_encode(['role' => 'user']),
            'new_values' => json_encode(['role' => fake()->randomElement(['user', 'admin'])]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Create a user delete log.
     */
    protected function createUserDeleteLog(User $admin, $users, \DateTimeInterface $createdAt): ?array
    {
        if ($users->isEmpty()) return null;

        $targetUser = $users->random();

        return [
            'admin_id' => $admin->id,
            'action' => 'delete',
            'model_type' => User::class,
            'model_id' => $targetUser->id,
            'description' => "Suppression de l'utilisateur {$targetUser->name}",
            'old_values' => json_encode(['id' => $targetUser->id, 'name' => $targetUser->name, 'email' => $targetUser->email]),
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Create an event view log.
     */
    protected function createEventViewLog(User $admin, $events, \DateTimeInterface $createdAt): ?array
    {
        if ($events->isEmpty()) return null;

        $event = $events->random();

        return [
            'admin_id' => $admin->id,
            'action' => 'view',
            'model_type' => Event::class,
            'model_id' => $event->id,
            'description' => "Consultation de l'événement {$event->title}",
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Create a template create log.
     */
    protected function createTemplateCreateLog(User $admin, $templates, \DateTimeInterface $createdAt): ?array
    {
        if ($templates->isEmpty()) return null;

        $template = $templates->random();

        return [
            'admin_id' => $admin->id,
            'action' => 'create',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
            'description' => "Création du template {$template->name}",
            'old_values' => null,
            'new_values' => json_encode(['name' => $template->name, 'event_type' => $template->event_type]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Create a template update log.
     */
    protected function createTemplateUpdateLog(User $admin, $templates, \DateTimeInterface $createdAt): ?array
    {
        if ($templates->isEmpty()) return null;

        $template = $templates->random();

        return [
            'admin_id' => $admin->id,
            'action' => 'update',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
            'description' => "Modification du template {$template->name}",
            'old_values' => json_encode(['name' => 'Ancien nom']),
            'new_values' => json_encode(['name' => $template->name]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Create a template toggle log.
     */
    protected function createTemplateToggleLog(User $admin, $templates, \DateTimeInterface $createdAt): ?array
    {
        if ($templates->isEmpty()) return null;

        $template = $templates->random();
        $newStatus = fake()->boolean();

        return [
            'admin_id' => $admin->id,
            'action' => 'toggle_active',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
            'description' => $newStatus
                ? "Activation du template {$template->name}"
                : "Désactivation du template {$template->name}",
            'old_values' => json_encode(['is_active' => !$newStatus]),
            'new_values' => json_encode(['is_active' => $newStatus]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Weighted random selection.
     */
    protected function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $random = fake()->numberBetween(1, $total);
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
