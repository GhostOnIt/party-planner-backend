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
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo user
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@partyplanner.com'],
            [
                'name' => 'Demo User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create additional users for collaboration
        $collaborators = collect();
        for ($i = 1; $i <= 3; $i++) {
            $collaborators->push(User::firstOrCreate(
                ['email' => "collaborator{$i}@partyplanner.com"],
                [
                    'name' => "Collaborator {$i}",
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            ));
        }

        // Create a wedding event (main demo event)
        $wedding = Event::firstOrCreate(
            [
                'user_id' => $demoUser->id,
                'title' => 'Mariage de Sophie & Thomas',
            ],
            [
                'type' => EventType::MARIAGE->value,
                'description' => 'Notre grand jour ! Un mariage champêtre dans le sud de la France.',
                'date' => now()->addMonths(3),
                'time' => '14:00',
                'location' => 'Domaine des Oliviers, Provence',
                'estimated_budget' => 2500000,
                'theme' => 'Champêtre Provençal',
                'expected_guests_count' => 120,
                'status' => EventStatus::UPCOMING->value,
            ]
        );

        // Only seed related data if the event was just created (no guests yet)
        if ($wedding->guests()->count() === 0) {
            // Add collaborators to wedding
            Collaborator::firstOrCreate(
                [
                    'event_id' => $wedding->id,
                    'user_id' => $collaborators[0]->id,
                ],
                [
                    'role' => CollaboratorRole::EDITOR->value,
                    'invited_at' => now()->subDays(10),
                    'accepted_at' => now()->subDays(9),
                ]
            );

            // Add guests to wedding
            $guestNames = [
                'Marie Dupont', 'Jean Martin', 'Claire Bernard', 'Pierre Dubois',
                'Isabelle Moreau', 'François Laurent', 'Nathalie Simon', 'Michel Lefebvre',
                'Sylvie Leroy', 'Philippe Girard', 'Catherine Roux', 'Alain Vincent',
            ];

            foreach ($guestNames as $index => $name) {
                $status = match ($index % 4) {
                    0 => RsvpStatus::ACCEPTED,
                    1 => RsvpStatus::PENDING,
                    2 => RsvpStatus::ACCEPTED,
                    3 => RsvpStatus::MAYBE,
                };

                $guest = Guest::firstOrCreate(
                    [
                        'event_id' => $wedding->id,
                        'name' => $name,
                    ],
                    [
                        'email' => Str::slug($name) . '@example.com',
                        'phone' => '06' . fake()->numerify('########'),
                        'rsvp_status' => $status->value,
                        'invitation_sent_at' => now()->subDays(rand(5, 20)),
                    ]
                );

                // Create invitation for guest
                Invitation::firstOrCreate(
                    [
                        'event_id' => $wedding->id,
                        'guest_id' => $guest->id,
                    ],
                    [
                        'token' => Str::random(32),
                        'sent_at' => $guest->invitation_sent_at,
                        'opened_at' => fake()->boolean(70) ? now()->subDays(rand(1, 5)) : null,
                        'responded_at' => $status !== RsvpStatus::PENDING ? now()->subDays(rand(1, 3)) : null,
                    ]
                );
            }

            // Add more random guests
            Guest::factory(30)->create(['event_id' => $wedding->id]);

            // Add tasks to wedding
            $weddingTasks = [
                ['title' => 'Réserver le domaine', 'status' => TaskStatus::COMPLETED, 'priority' => 'high'],
                ['title' => 'Choisir le traiteur', 'status' => TaskStatus::COMPLETED, 'priority' => 'high'],
                ['title' => 'Commander les faire-part', 'status' => TaskStatus::COMPLETED, 'priority' => 'medium'],
                ['title' => 'Réserver le photographe', 'status' => TaskStatus::IN_PROGRESS, 'priority' => 'high'],
                ['title' => 'Choisir le DJ', 'status' => TaskStatus::IN_PROGRESS, 'priority' => 'medium'],
                ['title' => 'Finaliser le menu', 'status' => TaskStatus::TODO, 'priority' => 'high'],
                ['title' => 'Commander le gâteau', 'status' => TaskStatus::TODO, 'priority' => 'medium'],
                ['title' => 'Organiser la décoration florale', 'status' => TaskStatus::TODO, 'priority' => 'medium'],
                ['title' => 'Planifier le plan de table', 'status' => TaskStatus::TODO, 'priority' => 'low'],
                ['title' => 'Réserver les chambres invités', 'status' => TaskStatus::TODO, 'priority' => 'low'],
            ];

            foreach ($weddingTasks as $taskData) {
                Task::firstOrCreate(
                    [
                        'event_id' => $wedding->id,
                        'title' => $taskData['title'],
                    ],
                    [
                        'assigned_to_user_id' => fake()->boolean(50) ? $demoUser->id : null,
                        'status' => $taskData['status']->value,
                        'priority' => $taskData['priority'],
                        'due_date' => now()->addDays(rand(7, 60)),
                        'completed_at' => $taskData['status'] === TaskStatus::COMPLETED ? now()->subDays(rand(1, 10)) : null,
                    ]
                );
            }

            // Add budget items to wedding
            $budgetItems = [
                ['category' => 'location', 'name' => 'Domaine des Oliviers', 'estimated' => 800000, 'actual' => 800000, 'paid' => true],
                ['category' => 'catering', 'name' => 'Traiteur Le Provençal', 'estimated' => 600000, 'actual' => 650000, 'paid' => true],
                ['category' => 'catering', 'name' => 'Vins et champagne', 'estimated' => 150000, 'actual' => null, 'paid' => false],
                ['category' => 'catering', 'name' => 'Pièce montée', 'estimated' => 80000, 'actual' => null, 'paid' => false],
                ['category' => 'decoration', 'name' => 'Fleurs et compositions', 'estimated' => 200000, 'actual' => 180000, 'paid' => true],
                ['category' => 'photography', 'name' => 'Photographe', 'estimated' => 250000, 'actual' => null, 'paid' => false],
                ['category' => 'photography', 'name' => 'Vidéaste', 'estimated' => 180000, 'actual' => null, 'paid' => false],
                ['category' => 'entertainment', 'name' => 'DJ', 'estimated' => 120000, 'actual' => null, 'paid' => false],
                ['category' => 'transportation', 'name' => 'Voiture mariés', 'estimated' => 50000, 'actual' => null, 'paid' => false],
                ['category' => 'other', 'name' => 'Alliances', 'estimated' => 150000, 'actual' => 145000, 'paid' => true],
            ];

            foreach ($budgetItems as $item) {
                BudgetItem::firstOrCreate(
                    [
                        'event_id' => $wedding->id,
                        'name' => $item['name'],
                    ],
                    [
                        'category' => $item['category'],
                        'estimated_cost' => $item['estimated'],
                        'actual_cost' => $item['actual'],
                        'paid' => $item['paid'],
                        'payment_date' => $item['paid'] ? now()->subDays(rand(10, 30)) : null,
                    ]
                );
            }

            // Add photos to wedding
            Photo::factory(8)->moodboard()->create(['event_id' => $wedding->id, 'uploaded_by_user_id' => $demoUser->id]);

            // Create subscription for wedding
            $subscription = Subscription::firstOrCreate(
                ['event_id' => $wedding->id],
                [
                    'user_id' => $demoUser->id,
                    'plan_type' => 'pro',
                    'base_price' => 15000,
                    'guest_count' => 120,
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
                    'transaction_reference' => 'MTN-' . Str::uuid(),
                    'status' => 'completed',
                    'metadata' => ['phone' => '677123456'],
                ]);
            }

            // Create notifications for demo user
            Notification::factory(5)->unread()->create(['user_id' => $demoUser->id, 'event_id' => $wedding->id]);
        }

        // Create a birthday event
        $birthday = Event::firstOrCreate(
            [
                'user_id' => $demoUser->id,
                'title' => 'Mes 30 ans',
            ],
            [
                'type' => EventType::ANNIVERSAIRE->value,
                'date' => now()->addMonths(1),
                'status' => EventStatus::UPCOMING->value,
                'expected_guests_count' => 50,
                'estimated_budget' => 300000,
            ]
        );

        if ($birthday->guests()->count() === 0) {
            Guest::factory(25)->create(['event_id' => $birthday->id]);
            Task::factory(5)->create(['event_id' => $birthday->id]);
            BudgetItem::factory(6)->create(['event_id' => $birthday->id]);
            Notification::factory(3)->read()->create(['user_id' => $demoUser->id, 'event_id' => $birthday->id]);
        }

        // Create a completed event
        $pastEvent = Event::firstOrCreate(
            [
                'user_id' => $demoUser->id,
                'title' => 'Réveillon 2024',
            ],
            [
                'type' => EventType::SOIREE->value,
                'date' => now()->subMonth(),
                'status' => EventStatus::COMPLETED->value,
                'expected_guests_count' => 40,
            ]
        );

        if ($pastEvent->guests()->count() === 0) {
            Guest::factory(40)->create(['event_id' => $pastEvent->id]);
            Photo::factory(15)->eventPhoto()->create(['event_id' => $pastEvent->id, 'uploaded_by_user_id' => $demoUser->id]);
        }

        $this->command->info('Demo data created successfully!');
        $this->command->info('Login with: demo@partyplanner.com / password');
    }
}
