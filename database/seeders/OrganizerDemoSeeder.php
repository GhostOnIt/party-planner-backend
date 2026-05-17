<?php

namespace Database\Seeders;

use App\Enums\CollaboratorRole;
use App\Enums\EventStatus;
use App\Enums\EventType;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Démo commerciale pour organisateurs d'événements (agences / planners pro).
 *
 * Parcours recommandé (15–20 min) :
 * 1. Login compte +1 → Dashboard
 * 2. Gala annuel → Invités / Tâches / Budget / Collaborateurs
 * 3. Mobile : /invitation/demo-organizer-rsvp
 * 4. Tablette : /check-in/demo-organizer-checkin
 * 5. Mariage Bamoussessa → multi-mandats
 * 6. Soirée Forum 2025 → check-in historique + photos
 * 7. Abonnements → MTN
 */
class OrganizerDemoSeeder extends Seeder
{
    private const DEMO_EMAIL_BASE = 'alexsonicka11@gmail.com';

    private const DEMO_PASSWORD = 'Test@1234';

    public const DEMO_RSVP_TOKEN = 'demo-organizer-rsvp';

    public const DEMO_CHECKIN_TOKEN = 'demo-organizer-checkin';

    private User $organizer;

    /** @var array<int, User> */
    private array $collaboratorUsers = [];

    public function run(): void
    {
        $this->seedOrganizerUser();
        $corporate = $this->seedCorporateEvent();
        $this->seedWeddingEvent();
        $this->seedPastEvent();
        $this->seedUserDefaults();
        $this->printDemoInfo($corporate);
    }

    private function demoEmail(int $suffix): string
    {
        [$local, $domain] = explode('@', self::DEMO_EMAIL_BASE, 2);

        return "{$local}+{$suffix}@{$domain}";
    }

    private function seedOrganizerUser(): void
    {
        $this->organizer = User::firstOrCreate(
            ['email' => $this->demoEmail(1)],
            [
                'name' => 'Agence Événements Congo',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ]
        );

        $collaboratorDefs = [
            2 => ['name' => 'Coordinateur Démo', 'role' => CollaboratorRole::COORDINATOR],
            3 => ['name' => 'Gestionnaire Invités Démo', 'role' => CollaboratorRole::GUEST_MANAGER],
            4 => ['name' => 'Comptable Démo', 'role' => CollaboratorRole::ACCOUNTANT],
        ];

        foreach ($collaboratorDefs as $suffix => $def) {
            $this->collaboratorUsers[$suffix] = User::firstOrCreate(
                ['email' => $this->demoEmail($suffix)],
                [
                    'name' => $def['name'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'email_verified_at' => now(),
                ]
            );
        }
    }

    private function seedCorporateEvent(): Event
    {
        $corporate = Event::firstOrCreate(
            [
                'user_id' => $this->organizer->id,
                'title' => 'Gala annuel des Partenaires 2026',
            ],
            [
                'type' => EventType::AUTRE->value,
                'description' => 'Gala corporate annuel réunissant partenaires institutionnels et entreprises. '
                    . 'Organisé par l\'agence pour le compte du comité des partenaires.',
                'date' => now()->addMonths(2),
                'time' => '19:00',
                'location' => 'Palais des Congrès, Brazzaville',
                'estimated_budget' => 8_000_000,
                'theme' => 'Excellence & Partenariat',
                'expected_guests_count' => 200,
                'status' => EventStatus::UPCOMING->value,
            ]
        );

        if ($corporate->guests()->count() > 0) {
            return $corporate;
        }

        $this->seedCorporateCollaborators($corporate);
        $this->seedCorporateGuests($corporate);
        $taskIds = $this->seedCorporateTasks($corporate);
        $this->seedCorporateBudget($corporate, $taskIds);
        Photo::factory(8)->moodboard()->create([
            'event_id' => $corporate->id,
            'uploaded_by_user_id' => $this->organizer->id,
        ]);
        $this->seedCorporateSubscription($corporate);
        Notification::factory(6)->unread()->create([
            'user_id' => $this->organizer->id,
            'event_id' => $corporate->id,
        ]);

        return $corporate;
    }

    private function seedCorporateCollaborators(Event $event): void
    {
        $roles = [
            2 => CollaboratorRole::COORDINATOR,
            3 => CollaboratorRole::GUEST_MANAGER,
            4 => CollaboratorRole::ACCOUNTANT,
        ];

        foreach ($roles as $suffix => $role) {
            Collaborator::firstOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $this->collaboratorUsers[$suffix]->id,
                ],
                [
                    'role' => $role->value,
                    'invited_at' => now()->subDays(14),
                    'accepted_at' => now()->subDays(13),
                ]
            );
        }
    }

    private function seedCorporateGuests(Event $event): void
    {
        $names = [
            'Grace Mabiala', 'Serge Okemba', 'Chantal Nzouba', 'Patrick Mouanda',
            'Irène Kimbembe', 'Alain Makaya', 'Béatrice Loubaki', 'Didier Ngoma',
            'Sandrine Itoua', 'Jean-Baptiste Oko', 'Marie-Claire Bissila', 'Hervé Massamba',
            'Pauline Matondo', 'Christian Elenga', 'Joséphine Mpassi', 'Rodrigue Kounkou',
            'Angèle Diongo', 'Franck Bouity', 'Sylvie Nkouka', 'Gérard Mbemba',
            'Clarisse Moukoko', 'Emmanuel Tchicaya', 'Henriette Loufoua', 'Bruno Ngatsé',
            'Véronique Makosso', 'Thierry Opoula', 'Nadège Kibambi', 'Fabrice Moutou',
            'Estelle Bouanga', 'Loïc Nguesso', 'Prisca Okoko', 'Yannick Malonga',
            'Diane Moussavou', 'Cédric Nzamba', 'Rachelle Kimbouendi', 'Arnaud Mabiala',
            'Olivia Tchibanga', 'Stéphane Loumouamou', 'Carine Nsonde', 'David Makita',
            'Florence Bouesso', 'Kevin Mavoungou', 'Aude Ngalou', 'Jordan Mfinanga',
            'Linda Mouanda', 'Marc Okemba', 'Inès Nkodia', 'Victor Loufoua',
            'Cynthia Mpassi', 'André Tchibinda', 'Mireille Ngoma', 'Samuel Kimbembe',
            'Audrey Moukala', 'Pascal Itoua', 'Julie Nzouba', 'Michel Elenga',
            'Stella Makaya', 'Richard Bissila', 'Hélène Massamba', 'Daniel Opoula',
            'Céline Loubaki', 'Eric Ngatsé', 'Fatou Mbemba', 'Olivier Moukoko',
            'Amina Tchicaya', 'Luc Nkouka', 'Brigitte Kounkou', 'Thomas Diongo',
            'Nathalie Bouity', 'Philippe Matondo', 'Isabelle Moutou', 'François Nguesso',
            'Catherine Okoko', 'Michel Malonga', 'Sylvie Moussavou', 'Alain Nzamba',
            'Nathalie Kimbouendi', 'Philippe Loumouamou', 'Isabelle Nsonde', 'François Makita',
            'Marie Bouesso', 'Jean Mavoungou', 'Claire Ngalou', 'Pierre Mfinanga',
            'Sophie Mouanda', 'Thomas Okemba', 'Julie Nkodia', 'Marc Loufoua',
            'Anne Mpassi', 'Paul Tchibinda', 'Lucie Ngoma', 'Henri Kimbembe',
        ];

        $statuses = $this->buildRsvpDistribution(count($names));

        foreach ($names as $index => $name) {
            $status = $statuses[$index];
            $this->createGuestWithInvitation($event, $name, $status, [
                'plus_one' => $index % 7 === 0,
                'plus_one_name' => $index % 7 === 0 ? 'Accompagnant ' . ($index + 1) : null,
                'dietary_restrictions' => $index % 11 === 0 ? 'Sans porc' : ($index % 13 === 0 ? 'Végétarien' : null),
                'notes' => $index % 17 === 0 ? 'VIP — table d\'honneur' : null,
            ]);
        }

        $rsvpGuest = Guest::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Invité Démo RSVP'],
            [
                'email' => 'demo-rsvp@example.com',
                'phone' => '066000001',
                'rsvp_status' => RsvpStatus::PENDING->value,
                'invitation_token' => self::DEMO_RSVP_TOKEN,
                'invitation_sent_at' => now()->subDays(3),
            ]
        );

        Invitation::firstOrCreate(
            ['event_id' => $event->id, 'guest_id' => $rsvpGuest->id],
            [
                'token' => Str::random(32),
                'sent_at' => $rsvpGuest->invitation_sent_at,
            ]
        );

        $checkinGuest = Guest::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Invité Démo Check-in'],
            [
                'email' => 'demo-checkin@example.com',
                'phone' => '066000002',
                'rsvp_status' => RsvpStatus::ACCEPTED->value,
                'invitation_token' => self::DEMO_CHECKIN_TOKEN,
                'invitation_sent_at' => now()->subDays(10),
                'checked_in' => false,
                'checked_in_at' => null,
            ]
        );

        Invitation::firstOrCreate(
            ['event_id' => $event->id, 'guest_id' => $checkinGuest->id],
            [
                'token' => Str::random(32),
                'sent_at' => $checkinGuest->invitation_sent_at,
                'responded_at' => now()->subDays(5),
            ]
        );
    }

    /**
     * @return list<RsvpStatus>
     */
    private function buildRsvpDistribution(int $count): array
    {
        $accepted = (int) round($count * 0.55);
        $pending = (int) round($count * 0.20);
        $maybe = (int) round($count * 0.15);
        $declined = max(0, $count - $accepted - $pending - $maybe);

        $pool = array_merge(
            array_fill(0, $accepted, RsvpStatus::ACCEPTED),
            array_fill(0, $pending, RsvpStatus::PENDING),
            array_fill(0, $maybe, RsvpStatus::MAYBE),
            array_fill(0, $declined, RsvpStatus::DECLINED),
        );

        shuffle($pool);

        return $pool;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createGuestWithInvitation(
        Event $event,
        string $name,
        RsvpStatus $status,
        array $extra = [],
    ): void {
        $guest = Guest::firstOrCreate(
            ['event_id' => $event->id, 'name' => $name],
            array_merge([
                'email' => Str::slug($name) . '@example.com',
                'phone' => '06' . fake()->numerify('########'),
                'rsvp_status' => $status->value,
                'invitation_sent_at' => now()->subDays(rand(5, 25)),
            ], $extra)
        );

        Invitation::firstOrCreate(
            ['event_id' => $event->id, 'guest_id' => $guest->id],
            [
                'token' => Str::random(32),
                'sent_at' => $guest->invitation_sent_at,
                'opened_at' => fake()->boolean(65) ? now()->subDays(rand(1, 8)) : null,
                'responded_at' => $status !== RsvpStatus::PENDING ? now()->subDays(rand(1, 5)) : null,
            ]
        );
    }

    /**
     * @return array<string, string> task title => task id
     */
    private function seedCorporateTasks(Event $event): array
    {
        $tasks = [
            [
                'title' => 'Réserver le Palais des Congrès',
                'status' => TaskStatus::COMPLETED,
                'priority' => 'high',
                'due_date' => now()->subDays(20),
                'estimated_cost' => 2_500_000,
                'budget_category' => 'location',
            ],
            [
                'title' => 'Signer contrat traiteur premium',
                'status' => TaskStatus::COMPLETED,
                'priority' => 'high',
                'due_date' => now()->subDays(15),
                'estimated_cost' => 2_000_000,
                'budget_category' => 'catering',
            ],
            [
                'title' => 'Brief équipe communication',
                'status' => TaskStatus::COMPLETED,
                'priority' => 'medium',
                'due_date' => now()->subDays(10),
                'estimated_cost' => 350_000,
                'budget_category' => 'other',
            ],
            [
                'title' => 'Valider plan de salle et seating VIP',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => 'high',
                'due_date' => now()->addDays(5),
                'estimated_cost' => null,
                'budget_category' => 'decoration',
            ],
            [
                'title' => 'Confirmer groupe musical',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => 'medium',
                'due_date' => now()->addDays(7),
                'estimated_cost' => 800_000,
                'budget_category' => 'entertainment',
            ],
            [
                'title' => 'Finaliser protocole accueil',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => 'high',
                'due_date' => now()->addDays(4),
                'estimated_cost' => null,
                'budget_category' => 'other',
            ],
            [
                'title' => 'Commander signalétique et roll-ups',
                'status' => TaskStatus::TODO,
                'priority' => 'medium',
                'due_date' => now()->addDays(14),
                'estimated_cost' => 450_000,
                'budget_category' => 'decoration',
            ],
            [
                'title' => 'Recruter équipe hôtesses',
                'status' => TaskStatus::TODO,
                'priority' => 'medium',
                'due_date' => now()->addDays(10),
                'estimated_cost' => 300_000,
                'budget_category' => 'other',
            ],
            [
                'title' => 'Tester système check-in tablette',
                'status' => TaskStatus::TODO,
                'priority' => 'high',
                'due_date' => now()->addDays(3),
                'estimated_cost' => null,
                'budget_category' => 'other',
            ],
            [
                'title' => 'Relancer invités en attente RSVP',
                'status' => TaskStatus::TODO,
                'priority' => 'high',
                'due_date' => now()->addDays(6),
                'estimated_cost' => null,
                'budget_category' => 'other',
            ],
            [
                'title' => 'Coordonner sécurité et accès',
                'status' => TaskStatus::TODO,
                'priority' => 'medium',
                'due_date' => now()->addDays(12),
                'estimated_cost' => 600_000,
                'budget_category' => 'other',
            ],
            [
                'title' => 'Préparer dossier presse',
                'status' => TaskStatus::TODO,
                'priority' => 'low',
                'due_date' => now()->addDays(20),
                'estimated_cost' => 200_000,
                'budget_category' => 'other',
            ],
        ];

        $taskIds = [];

        foreach ($tasks as $taskData) {
            $status = $taskData['status'];
            $task = Task::firstOrCreate(
                [
                    'event_id' => $event->id,
                    'title' => $taskData['title'],
                ],
                [
                    'assigned_to_user_id' => fake()->boolean(40)
                        ? $this->collaboratorUsers[2]->id
                        : $this->organizer->id,
                    'status' => $status->value,
                    'priority' => $taskData['priority'],
                    'due_date' => $taskData['due_date'],
                    'completed_at' => $status === TaskStatus::COMPLETED ? now()->subDays(rand(1, 8)) : null,
                    'estimated_cost' => $taskData['estimated_cost'],
                    'budget_category' => $taskData['budget_category'],
                ]
            );
            $taskIds[$taskData['title']] = $task->id;
        }

        return $taskIds;
    }

    /**
     * @param  array<string, string>  $taskIds
     */
    private function seedCorporateBudget(Event $event, array $taskIds): void
    {
        $items = [
            [
                'category' => 'location',
                'name' => 'Palais des Congrès — location salle',
                'estimated' => 2_500_000,
                'actual' => 2_500_000,
                'paid' => true,
                'vendor_name' => 'Palais des Congrès',
                'task_title' => 'Réserver le Palais des Congrès',
            ],
            [
                'category' => 'catering',
                'name' => 'Traiteur premium — cocktail & dîner',
                'estimated' => 2_000_000,
                'actual' => 2_150_000,
                'paid' => true,
                'vendor_name' => 'Saveurs du Congo',
                'task_title' => 'Signer contrat traiteur premium',
            ],
            [
                'category' => 'entertainment',
                'name' => 'Groupe musical live',
                'estimated' => 800_000,
                'actual' => null,
                'paid' => false,
                'vendor_name' => 'Afro Groove Band',
                'task_title' => 'Confirmer groupe musical',
            ],
            [
                'category' => 'decoration',
                'name' => 'Décoration scène & floral',
                'estimated' => 650_000,
                'actual' => 620_000,
                'paid' => true,
                'vendor_name' => 'Déco Élégance',
                'task_title' => null,
            ],
            [
                'category' => 'decoration',
                'name' => 'Signalétique et roll-ups',
                'estimated' => 450_000,
                'actual' => null,
                'paid' => false,
                'vendor_name' => 'Print Congo',
                'task_title' => 'Commander signalétique et roll-ups',
            ],
            [
                'category' => 'other',
                'name' => 'Agence communication & RP',
                'estimated' => 350_000,
                'actual' => 350_000,
                'paid' => true,
                'vendor_name' => 'Media Plus',
                'task_title' => 'Brief équipe communication',
            ],
            [
                'category' => 'other',
                'name' => 'Sécurité et accès',
                'estimated' => 600_000,
                'actual' => null,
                'paid' => false,
                'vendor_name' => 'Secure Events CG',
                'task_title' => 'Coordonner sécurité et accès',
            ],
            [
                'category' => 'other',
                'name' => 'Équipe hôtesses (20)',
                'estimated' => 300_000,
                'actual' => null,
                'paid' => false,
                'vendor_name' => 'Hostess Pro',
                'task_title' => 'Recruter équipe hôtesses',
            ],
            [
                'category' => 'photography',
                'name' => 'Photographe & vidéaste',
                'estimated' => 500_000,
                'actual' => null,
                'paid' => false,
                'vendor_name' => 'Studio Brazza',
                'task_title' => null,
            ],
            [
                'category' => 'transportation',
                'name' => 'Navettes VIP partenaires',
                'estimated' => 350_000,
                'actual' => 340_000,
                'paid' => true,
                'vendor_name' => 'Trans Congo',
                'task_title' => null,
            ],
        ];

        foreach ($items as $item) {
            $taskId = isset($item['task_title'], $taskIds[$item['task_title']])
                ? $taskIds[$item['task_title']]
                : null;

            BudgetItem::firstOrCreate(
                [
                    'event_id' => $event->id,
                    'name' => $item['name'],
                ],
                [
                    'category' => $item['category'],
                    'estimated_cost' => $item['estimated'],
                    'actual_cost' => $item['actual'],
                    'paid' => $item['paid'],
                    'payment_date' => $item['paid'] ? now()->subDays(rand(5, 25)) : null,
                    'vendor_name' => $item['vendor_name'],
                    'task_id' => $taskId,
                ]
            );
        }
    }

    private function seedCorporateSubscription(Event $event): void
    {
        $subscription = Subscription::firstOrCreate(
            ['event_id' => $event->id],
            [
                'user_id' => $this->organizer->id,
                'plan_type' => 'pro',
                'base_price' => 15000,
                'guest_count' => 200,
                'guest_price_per_unit' => 30,
                'total_price' => 15000,
                'payment_status' => 'paid',
                'payment_method' => 'mtn_mobile_money',
                'expires_at' => now()->addYear(),
            ]
        );

        if ($subscription->payments()->count() === 0) {
            Payment::create([
                'subscription_id' => $subscription->id,
                'amount' => 15000,
                'currency' => 'XAF',
                'payment_method' => 'mtn_mobile_money',
                'transaction_reference' => 'MTN-DEMO-' . Str::uuid(),
                'status' => 'completed',
                'metadata' => ['phone' => '066123456'],
            ]);
        }
    }

    private function seedWeddingEvent(): void
    {
        $wedding = Event::firstOrCreate(
            [
                'user_id' => $this->organizer->id,
                'title' => 'Mariage Bamoussessa — Client M. & Mme Okemba',
            ],
            [
                'type' => EventType::MARIAGE->value,
                'description' => 'Mariage traditionnel et réception. '
                    . 'Événement géré par l\'agence pour le compte de M. et Mme Okemba.',
                'date' => now()->addMonths(4),
                'time' => '15:00',
                'location' => 'Domaine La Corniche, Pointe-Noire',
                'estimated_budget' => 4_500_000,
                'theme' => 'Élégance africaine contemporaine',
                'expected_guests_count' => 60,
                'status' => EventStatus::UPCOMING->value,
            ]
        );

        if ($wedding->guests()->count() > 0) {
            return;
        }

        $guestNames = [
            'Famille Okemba', 'Famille Bamoussessa', 'Oncle Jean Okemba', 'Tante Marie Bamoussessa',
            'Cousin Patrick Okemba', 'Cousine Grace Bamoussessa', 'Ami Serge Mouanda', 'Amie Chantal Nzouba',
            'Collègue Alain Makaya', 'Voisine Irène Kimbembe', 'Parrain Christian Elenga', 'Marraine Josée Mpassi',
        ];

        foreach ($guestNames as $index => $name) {
            $status = match ($index % 4) {
                0 => RsvpStatus::ACCEPTED,
                1 => RsvpStatus::PENDING,
                2 => RsvpStatus::ACCEPTED,
                default => RsvpStatus::MAYBE,
            };
            $this->createGuestWithInvitation($wedding, $name, $status);
        }

        Guest::factory(48)->create(['event_id' => $wedding->id]);

        $weddingTasks = [
            ['title' => 'Réserver le domaine La Corniche', 'status' => TaskStatus::COMPLETED, 'priority' => 'high'],
            ['title' => 'Choisir le traiteur', 'status' => TaskStatus::COMPLETED, 'priority' => 'high'],
            ['title' => 'Réserver le DJ', 'status' => TaskStatus::IN_PROGRESS, 'priority' => 'medium'],
            ['title' => 'Décoration florale', 'status' => TaskStatus::IN_PROGRESS, 'priority' => 'medium'],
            ['title' => 'Contrat photographe', 'status' => TaskStatus::TODO, 'priority' => 'high'],
            ['title' => 'Plan de table', 'status' => TaskStatus::TODO, 'priority' => 'low'],
        ];

        foreach ($weddingTasks as $taskData) {
            Task::firstOrCreate(
                ['event_id' => $wedding->id, 'title' => $taskData['title']],
                [
                    'assigned_to_user_id' => $this->organizer->id,
                    'status' => $taskData['status']->value,
                    'priority' => $taskData['priority'],
                    'due_date' => now()->addDays(rand(14, 90)),
                    'completed_at' => $taskData['status'] === TaskStatus::COMPLETED
                        ? now()->subDays(rand(1, 12))
                        : null,
                ]
            );
        }

        $weddingBudget = [
            ['category' => 'location', 'name' => 'Domaine La Corniche', 'estimated' => 1_200_000, 'actual' => 1_200_000, 'paid' => true],
            ['category' => 'catering', 'name' => 'Traiteur réception', 'estimated' => 1_500_000, 'actual' => null, 'paid' => false],
            ['category' => 'decoration', 'name' => 'Fleurs et arche', 'estimated' => 400_000, 'actual' => 380_000, 'paid' => true],
            ['category' => 'entertainment', 'name' => 'DJ & sono', 'estimated' => 350_000, 'actual' => null, 'paid' => false],
            ['category' => 'photography', 'name' => 'Photographe & vidéo', 'estimated' => 450_000, 'actual' => null, 'paid' => false],
            ['category' => 'other', 'name' => 'Tenues & accessoires', 'estimated' => 200_000, 'actual' => 195_000, 'paid' => true],
        ];

        foreach ($weddingBudget as $item) {
            BudgetItem::firstOrCreate(
                ['event_id' => $wedding->id, 'name' => $item['name']],
                [
                    'category' => $item['category'],
                    'estimated_cost' => $item['estimated'],
                    'actual_cost' => $item['actual'],
                    'paid' => $item['paid'],
                    'payment_date' => $item['paid'] ? now()->subDays(rand(5, 20)) : null,
                ]
            );
        }

        Photo::factory(4)->moodboard()->create([
            'event_id' => $wedding->id,
            'uploaded_by_user_id' => $this->organizer->id,
        ]);
    }

    private function seedPastEvent(): void
    {
        $past = Event::firstOrCreate(
            [
                'user_id' => $this->organizer->id,
                'title' => 'Soirée de clôture Forum Économique 2025',
            ],
            [
                'type' => EventType::SOIREE->value,
                'description' => 'Soirée de clôture du forum — bilan et networking des participants.',
                'date' => now()->subWeeks(3),
                'time' => '20:00',
                'location' => 'Hôtel Radisson Blu, Brazzaville',
                'estimated_budget' => 2_000_000,
                'expected_guests_count' => 40,
                'status' => EventStatus::COMPLETED->value,
            ]
        );

        if ($past->guests()->count() > 0) {
            return;
        }

        $pastNames = [
            'Delegate A. Mouanda', 'Delegate B. Okemba', 'Delegate C. Nzouba', 'Delegate D. Makaya',
            'Delegate E. Kimbembe', 'Delegate F. Loubaki', 'Delegate G. Ngoma', 'Delegate H. Itoua',
            'Delegate I. Mpassi', 'Delegate J. Elenga', 'Delegate K. Bouity', 'Delegate L. Nkouka',
        ];

        foreach ($pastNames as $name) {
            Guest::firstOrCreate(
                ['event_id' => $past->id, 'name' => $name],
                [
                    'email' => Str::slug($name) . '@example.com',
                    'rsvp_status' => RsvpStatus::ACCEPTED->value,
                    'checked_in' => true,
                    'checked_in_at' => $past->date->copy()->setTime(20, rand(0, 59)),
                    'invitation_sent_at' => $past->date->copy()->subWeeks(2),
                ]
            );
        }

        Guest::factory(28)
            ->accepted()
            ->create(['event_id' => $past->id])
            ->each(function (Guest $guest) use ($past) {
                $guest->update([
                    'checked_in' => true,
                    'checked_in_at' => $past->date->copy()->setTime(20, rand(0, 59)),
                ]);
            });

        Photo::factory(14)->eventPhoto()->create([
            'event_id' => $past->id,
            'uploaded_by_user_id' => $this->organizer->id,
        ]);
    }

    private function seedUserDefaults(): void
    {
        $this->call([
            UserBudgetCategoriesSeeder::class,
            UserEventTypesSeeder::class,
            UserCollaboratorRolesSeeder::class,
        ]);
    }

    private function printDemoInfo(Event $corporate): void
    {
        $frontend = rtrim(config('app.frontend_url', config('app.url')), '/');

        $this->command->info('');
        $this->command->info('=== Démo organisateurs créée avec succès ===');
        $this->command->info('');
        $this->command->info('Compte principal (agence) :');
        $this->command->info('  Email    : ' . $this->demoEmail(1));
        $this->command->info('  Password : ' . self::DEMO_PASSWORD);
        $this->command->info('');
        $this->command->info('Collaborateurs (OTP sur la même boîte mail) :');
        $this->command->info('  +2 Coordinateur : ' . $this->demoEmail(2));
        $this->command->info('  +3 Invités      : ' . $this->demoEmail(3));
        $this->command->info('  +4 Comptable    : ' . $this->demoEmail(4));
        $this->command->info('');
        $this->command->info('Événements :');
        $this->command->info('  • ' . $corporate->title);
        $this->command->info('  • Mariage Bamoussessa — Client M. & Mme Okemba');
        $this->command->info('  • Soirée de clôture Forum Économique 2025');
        $this->command->info('');
        $this->command->info('URLs démo live :');
        $this->command->info('  RSVP     : ' . $frontend . '/invitation/' . self::DEMO_RSVP_TOKEN);
        $this->command->info('  Check-in : ' . $frontend . '/check-in/' . self::DEMO_CHECKIN_TOKEN);
        $this->command->info('');
    }
}
