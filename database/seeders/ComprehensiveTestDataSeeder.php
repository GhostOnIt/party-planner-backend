<?php

namespace Database\Seeders;

use App\Enums\BudgetCategory;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\PlanType;
use App\Enums\RsvpStatus;
use App\Enums\TaskStatus;
use App\Models\BudgetItem;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Photo;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use function fake;

class ComprehensiveTestDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run the DatabaseSeeder first.');
            return;
        }

        $this->command->info("Creating comprehensive test data for {$users->count()} users...");

        // Get all users except the one we might use as collaborator source
        $allUsers = $users->pluck('id')->toArray();

        foreach ($users as $user) {
            $this->command->info("  Processing user: {$user->name}");
            $this->createDataForUser($user, $allUsers);
        }

        $this->command->info('Comprehensive test data created successfully!');
    }

    /**
     * Create comprehensive data for a single user.
     */
    protected function createDataForUser(User $user, array $allUserIds): void
    {
        // Create 5-7 events per user
        $eventCount = fake()->numberBetween(5, 7);
        $events = $this->createEventsForUser($user, $eventCount);

        foreach ($events as $event) {
            // Create guests (50-350 per event)
            $guestCount = fake()->numberBetween(50, 350);
            $this->createGuestsForEvent($event, $guestCount);

            // Create tasks (8-15 per event)
            $taskCount = fake()->numberBetween(8, 15);
            $this->createTasksForEvent($event, $taskCount);

            // Create budget items (6-12 per event)
            $budgetItemCount = fake()->numberBetween(6, 12);
            $this->createBudgetItemsForEvent($event, $budgetItemCount);

            // Create photos (5-20 per event)
            $photoCount = fake()->numberBetween(5, 20);
            $this->createPhotosForEvent($event, $user, $photoCount);

            // Create collaborators (0-3 per event)
            $collaboratorCount = fake()->numberBetween(0, 3);
            $this->createCollaboratorsForEvent($event, $user, $allUserIds, $collaboratorCount);

            // Create notifications (3-8 per event)
            $notificationCount = fake()->numberBetween(3, 8);
            $this->createNotificationsForUser($user, $event, $notificationCount);

            // 70% chance of having a subscription
            if (fake()->boolean(70)) {
                $this->createSubscriptionForEvent($event, $user);
            }
        }
    }

    /**
     * Create events for a user with varied types and statuses.
     */
    protected function createEventsForUser(User $user, int $count): array
    {
        $events = [];

        // Event type distribution
        $typeDistribution = [
            ['type' => EventType::MARIAGE, 'count' => fake()->numberBetween(1, 2)],
            ['type' => EventType::ANNIVERSAIRE, 'count' => fake()->numberBetween(1, 2)],
            ['type' => EventType::BABY_SHOWER, 'count' => 1],
            ['type' => EventType::SOIREE, 'count' => 1],
            ['type' => EventType::BRUNCH, 'count' => fake()->numberBetween(0, 1)],
        ];

        // Status distribution: 60% upcoming, 20% ongoing, 15% completed, 5% cancelled
        $statusWeights = [
            'upcoming' => 60,
            'ongoing' => 20,
            'completed' => 15,
            'cancelled' => 5,
        ];

        $created = 0;
        foreach ($typeDistribution as $item) {
            if ($created >= $count) break;

            for ($i = 0; $i < $item['count'] && $created < $count; $i++) {
                $status = $this->weightedRandomStatusString($statusWeights);
                $event = $this->createEvent($user, $item['type'], $status);
                $events[] = $event;
                $created++;
            }
        }

        // Fill remaining slots with random types
        while ($created < $count) {
            $type = fake()->randomElement(EventType::cases());
            $status = $this->weightedRandomStatusString($statusWeights);
            $events[] = $this->createEvent($user, $type, $status);
            $created++;
        }

        return $events;
    }

    /**
     * Create a single event.
     */
    protected function createEvent(User $user, EventType $type, string $status): Event
    {
        $budgetRanges = [
            EventType::MARIAGE->value => [1000000, 10000000],
            EventType::ANNIVERSAIRE->value => [100000, 500000],
            EventType::BABY_SHOWER->value => [50000, 300000],
            EventType::SOIREE->value => [100000, 1000000],
            EventType::BRUNCH->value => [30000, 150000],
            EventType::AUTRE->value => [50000, 500000],
        ];

        $guestRanges = [
            EventType::MARIAGE->value => [50, 350],
            EventType::ANNIVERSAIRE->value => [50, 150],
            EventType::BABY_SHOWER->value => [30, 80],
            EventType::SOIREE->value => [50, 200],
            EventType::BRUNCH->value => [20, 50],
            EventType::AUTRE->value => [20, 100],
        ];

        $budgetRange = $budgetRanges[$type->value] ?? [50000, 500000];
        $guestRange = $guestRanges[$type->value] ?? [50, 150];

        $date = $status === 'completed'
            ? fake()->dateTimeBetween('-6 months', '-1 day')
            : fake()->dateTimeBetween('now', '+6 months');

        return Event::create([
            'user_id' => $user->id,
            'title' => $this->generateEventTitle($type),
            'type' => $type->value,
            'description' => fake()->optional(0.7)->paragraph(),
            'date' => $date,
            'time' => fake()->optional(0.8)->time('H:i'),
            'location' => fake()->optional(0.8)->address(),
            'estimated_budget' => fake()->numberBetween($budgetRange[0], $budgetRange[1]),
            'theme' => fake()->optional(0.5)->randomElement([
                'Bohème', 'Champêtre', 'Moderne', 'Vintage', 'Tropical',
                'Élégant', 'Rustique', 'Minimaliste', 'Glamour', 'Nature'
            ]),
            'expected_guests_count' => fake()->numberBetween($guestRange[0], $guestRange[1]),
            'status' => $status,
        ]);
    }

    /**
     * Generate event title based on type.
     */
    protected function generateEventTitle(EventType $type): string
    {
        return match ($type) {
            EventType::MARIAGE => fake()->randomElement([
                'Mariage de ' . fake()->firstName() . ' & ' . fake()->firstName(),
                'Notre mariage',
                'Wedding ' . fake()->lastName(),
            ]),
            EventType::ANNIVERSAIRE => fake()->randomElement([
                'Anniversaire de ' . fake()->firstName(),
                fake()->numberBetween(18, 60) . ' ans de ' . fake()->firstName(),
                'Fête d\'anniversaire',
            ]),
            EventType::BABY_SHOWER => fake()->randomElement([
                'Baby Shower de ' . fake()->firstNameFemale(),
                'Bienvenue bébé ' . fake()->lastName(),
            ]),
            EventType::SOIREE => fake()->randomElement([
                'Soirée ' . fake()->word(),
                'Grande soirée',
                'Fête de fin d\'année',
            ]),
            EventType::BRUNCH => fake()->randomElement([
                'Brunch dominical',
                'Brunch entre amis',
            ]),
            EventType::AUTRE => 'Événement spécial',
        };
    }

    /**
     * Create guests for an event with varied RSVP statuses.
     */
    protected function createGuestsForEvent(Event $event, int $count): void
    {
        // Distribution: 40% accepted, 30% pending, 20% declined, 10% maybe
        $statusDistribution = [
            RsvpStatus::ACCEPTED->value => (int) ($count * 0.40),
            RsvpStatus::PENDING->value => (int) ($count * 0.30),
            RsvpStatus::DECLINED->value => (int) ($count * 0.20),
            RsvpStatus::MAYBE->value => (int) ($count * 0.10),
        ];

        $guests = [];
        foreach ($statusDistribution as $status => $statusCount) {
            for ($i = 0; $i < $statusCount; $i++) {
                $checkedIn = $status === RsvpStatus::ACCEPTED->value && fake()->boolean(30);
                $guests[] = [
                    'event_id' => $event->id,
                    'name' => fake()->name(),
                    'email' => fake()->optional(0.8)->safeEmail(),
                    'phone' => fake()->optional(0.6)->phoneNumber(),
                    'rsvp_status' => $status,
                    'checked_in' => $checkedIn,
                    'checked_in_at' => $checkedIn ? fake()->dateTimeBetween('-1 month', 'now') : null,
                    'invitation_sent_at' => fake()->optional(0.7)->dateTimeBetween('-2 months', 'now'),
                    'reminder_sent_at' => fake()->optional(0.3)->dateTimeBetween('-1 month', 'now'),
                    'notes' => fake()->optional(0.1)->sentence(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Batch insert for performance
        foreach (array_chunk($guests, 100) as $chunk) {
            Guest::insert($chunk);
        }

        // Create invitations for 80% of guests
        $guestIds = Guest::where('event_id', $event->id)->pluck('id');
        $invitationCount = (int) ($guestIds->count() * 0.8);
        $invitationGuestIds = $guestIds->random(min($invitationCount, $guestIds->count()));

        $invitations = [];
        foreach ($invitationGuestIds as $guestId) {
            $sent = fake()->boolean(70);
            $opened = $sent && fake()->boolean(50);
            $responded = $opened && fake()->boolean(60);

            $invitations[] = [
                'event_id' => $event->id,
                'guest_id' => $guestId,
                'token' => \Illuminate\Support\Str::random(32),
                'sent_at' => $sent ? fake()->dateTimeBetween('-1 month', 'now') : null,
                'opened_at' => $opened ? fake()->dateTimeBetween('-3 weeks', 'now') : null,
                'responded_at' => $responded ? fake()->dateTimeBetween('-2 weeks', 'now') : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($invitations, 100) as $chunk) {
            Invitation::insert($chunk);
        }
    }

    /**
     * Create tasks for an event with varied statuses.
     */
    protected function createTasksForEvent(Event $event, int $count): void
    {
        // Distribution: 30% completed, 40% in_progress, 30% todo
        $statusDistribution = [
            TaskStatus::COMPLETED->value => (int) ($count * 0.30),
            TaskStatus::IN_PROGRESS->value => (int) ($count * 0.40),
            TaskStatus::TODO->value => (int) ($count * 0.30),
        ];

        $taskTitles = [
            'Réserver le lieu', 'Contacter le traiteur', 'Envoyer les invitations',
            'Choisir le gâteau', 'Réserver le photographe', 'Organiser la décoration',
            'Prévoir la musique/DJ', 'Confirmer les invités', 'Planifier le menu',
            'Louer le matériel', 'Organiser le transport', 'Préparer les cadeaux',
            'Finaliser le plan de table', 'Réserver hébergement', 'Commander les fleurs',
        ];

        $tasks = [];
        $titleIndex = 0;

        foreach ($statusDistribution as $status => $statusCount) {
            for ($i = 0; $i < $statusCount; $i++) {
                $isCompleted = $status === TaskStatus::COMPLETED->value;
                $tasks[] = [
                    'event_id' => $event->id,
                    'title' => $taskTitles[$titleIndex % count($taskTitles)],
                    'description' => fake()->optional(0.4)->paragraph(),
                    'status' => $status,
                    'priority' => fake()->randomElement(['low', 'medium', 'high']),
                    'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+3 months'),
                    'completed_at' => $isCompleted ? fake()->dateTimeBetween('-1 month', 'now') : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $titleIndex++;
            }
        }

        Task::insert($tasks);
    }

    /**
     * Create budget items for an event.
     */
    protected function createBudgetItemsForEvent(Event $event, int $count): void
    {
        $categories = BudgetCategory::cases();
        $itemTemplates = [
            'location' => ['Salle de réception', 'Location terrain', 'Frais de réservation'],
            'catering' => ['Menu entrée', 'Plat principal', 'Desserts', 'Boissons'],
            'decoration' => ['Fleurs', 'Ballons', 'Nappes et serviettes', 'Centre de table'],
            'entertainment' => ['DJ', 'Groupe de musique', 'Animation', 'Photobooth'],
            'photography' => ['Photographe', 'Vidéaste', 'Album photo'],
            'transportation' => ['Location voiture', 'Bus invités', 'Parking'],
            'other' => ['Cadeaux invités', 'Assurance', 'Pourboires'],
        ];

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $category = fake()->randomElement($categories);
            $templates = $itemTemplates[$category->value] ?? ['Autre'];
            $estimatedCost = fake()->numberBetween(10000, 500000);
            $hasActualCost = fake()->boolean(60);
            $actualCost = $hasActualCost ? $estimatedCost * fake()->randomFloat(2, 0.8, 1.3) : null;
            $paid = $hasActualCost && fake()->boolean(60);

            $items[] = [
                'event_id' => $event->id,
                'category' => $category->value,
                'name' => fake()->randomElement($templates),
                'estimated_cost' => $estimatedCost,
                'actual_cost' => $actualCost,
                'paid' => $paid,
                'payment_date' => $paid ? fake()->dateTimeBetween('-2 months', 'now') : null,
                'notes' => fake()->optional(0.2)->sentence(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        BudgetItem::insert($items);
    }

    /**
     * Create photos for an event.
     */
    protected function createPhotosForEvent(Event $event, User $user, int $count): void
    {
        $photos = [];
        for ($i = 0; $i < $count; $i++) {
            $photos[] = [
                'event_id' => $event->id,
                'uploaded_by_user_id' => $user->id,
                'url' => 'https://picsum.photos/800/600?random=' . fake()->uuid(),
                'thumbnail_url' => 'https://picsum.photos/200/150?random=' . fake()->uuid(),
                'type' => fake()->randomElement(['moodboard', 'event_photo']),
                'description' => fake()->optional(0.5)->sentence(),
                'is_featured' => fake()->boolean(10),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Photo::insert($photos);
    }

    /**
     * Create collaborators for an event.
     */
    protected function createCollaboratorsForEvent(Event $event, User $owner, array $allUserIds, int $count): void
    {
        if ($count === 0) return;

        // Get users that are not the owner
        $availableUserIds = array_diff($allUserIds, [$owner->id]);
        if (empty($availableUserIds)) return;

        $collaboratorIds = fake()->randomElements($availableUserIds, min($count, count($availableUserIds)));

        $collaborators = [];
        foreach ($collaboratorIds as $userId) {
            $accepted = fake()->boolean(80);
            $collaborators[] = [
                'event_id' => $event->id,
                'user_id' => $userId,
                'role' => fake()->randomElement(['editor', 'viewer']),
                'accepted_at' => $accepted ? fake()->dateTimeBetween('-1 month', 'now') : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Collaborator::insert($collaborators);
    }

    /**
     * Create notifications for a user related to an event.
     */
    protected function createNotificationsForUser(User $user, Event $event, int $count): void
    {
        // Valid enum values from migration
        $types = ['task_reminder', 'guest_reminder', 'budget_alert', 'event_reminder', 'collaboration_invite'];

        $notifications = [];
        for ($i = 0; $i < $count; $i++) {
            $read = fake()->boolean(50);
            $type = fake()->randomElement($types);
            $notifications[] = [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'type' => $type,
                'title' => $this->getNotificationTitle($type, $event),
                'message' => $this->getNotificationMessage($type, $event),
                'read_at' => $read ? fake()->dateTimeBetween('-1 week', 'now') : null,
                'sent_via' => fake()->randomElement(['email', 'push']),
                'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
                'updated_at' => now(),
            ];
        }

        Notification::insert($notifications);
    }

    /**
     * Get notification title based on type.
     */
    protected function getNotificationTitle(string $type, Event $event): string
    {
        return match ($type) {
            'task_reminder' => "Rappel de tâche pour {$event->title}",
            'guest_reminder' => "Rappel: Invités en attente de réponse",
            'budget_alert' => "Alerte budget pour {$event->title}",
            'event_reminder' => "Rappel: {$event->title} approche",
            'collaboration_invite' => "Invitation à collaborer sur {$event->title}",
            default => "Notification pour {$event->title}",
        };
    }

    /**
     * Get notification message based on type.
     */
    protected function getNotificationMessage(string $type, Event $event): string
    {
        return match ($type) {
            'task_reminder' => "Vous avez des tâches en attente pour l'événement {$event->title}.",
            'guest_reminder' => "Certains invités n'ont pas encore répondu à votre invitation.",
            'budget_alert' => "Le budget de votre événement {$event->title} nécessite votre attention.",
            'event_reminder' => "Votre événement {$event->title} aura lieu bientôt.",
            'collaboration_invite' => "Vous avez été invité à collaborer sur {$event->title}.",
            default => fake()->sentence(),
        };
    }

    /**
     * Create subscription and payments for an event.
     */
    protected function createSubscriptionForEvent(Event $event, User $user): void
    {
        // 50% starter, 50% pro
        $planType = fake()->boolean(50) ? PlanType::STARTER : PlanType::PRO;

        $guestCount = Guest::where('event_id', $event->id)->count();
        $extraGuests = max(0, $guestCount - $planType->includedGuests());
        $guestPrice = $extraGuests * $planType->pricePerExtraGuest();
        $totalPrice = $planType->basePrice() + $guestPrice;

        // Payment status: 80% paid, 15% pending, 5% failed (DB values: 'pending', 'paid', 'failed', 'refunded')
        $paymentStatusWeights = [
            ['status' => 'paid', 'weight' => 80],
            ['status' => 'pending', 'weight' => 15],
            ['status' => 'failed', 'weight' => 5],
        ];
        $paymentStatus = $this->weightedRandomString($paymentStatusWeights);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'plan_type' => $planType->value,
            'base_price' => $planType->basePrice(),
            'guest_count' => $guestCount,
            'guest_price_per_unit' => $planType->pricePerExtraGuest(),
            'total_price' => $totalPrice,
            'payment_status' => $paymentStatus,
            'payment_method' => fake()->randomElement(['mtn_mobile_money', 'airtel_money']),
            'payment_reference' => $paymentStatus === 'paid' ? fake()->uuid() : null,
            'expires_at' => $paymentStatus === 'paid'
                ? fake()->dateTimeBetween('+6 months', '+1 year')
                : null,
        ]);

        // Create associated payment
        if ($paymentStatus !== 'pending') {
            Payment::create([
                'subscription_id' => $subscription->id,
                'amount' => $totalPrice,
                'payment_method' => $subscription->payment_method,
                'status' => $paymentStatus === 'paid' ? 'completed' : 'failed',
                'transaction_reference' => fake()->uuid(),
                'metadata' => json_encode(['status' => $paymentStatus, 'processed_at' => now()->toIso8601String()]),
            ]);
        }
    }

    /**
     * Get weighted random status string.
     */
    protected function weightedRandomStatusString(array $weights): string
    {
        $total = array_sum($weights);
        $random = fake()->numberBetween(1, $total);
        $cumulative = 0;

        foreach ($weights as $status => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $status;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Get weighted random string from array of objects.
     */
    protected function weightedRandomString(array $weights): string
    {
        $total = array_sum(array_column($weights, 'weight'));
        $random = fake()->numberBetween(1, $total);
        $cumulative = 0;

        foreach ($weights as $item) {
            $cumulative += $item['weight'];
            if ($random <= $cumulative) {
                return $item['status'];
            }
        }

        return $weights[0]['status'];
    }
}
