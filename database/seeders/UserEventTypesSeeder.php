<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Models\User;
use App\Models\UserEventType;
use Illuminate\Database\Seeder;

class UserEventTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultTypes = [
            [
                'slug' => EventType::MARIAGE->value,
                'name' => EventType::MARIAGE->label(),
                'color' => EventType::MARIAGE->color(),
                'order' => 1,
            ],
            [
                'slug' => EventType::ANNIVERSAIRE->value,
                'name' => EventType::ANNIVERSAIRE->label(),
                'color' => EventType::ANNIVERSAIRE->color(),
                'order' => 2,
            ],
            [
                'slug' => EventType::BABY_SHOWER->value,
                'name' => EventType::BABY_SHOWER->label(),
                'color' => EventType::BABY_SHOWER->color(),
                'order' => 3,
            ],
            [
                'slug' => EventType::SOIREE->value,
                'name' => EventType::SOIREE->label(),
                'color' => EventType::SOIREE->color(),
                'order' => 4,
            ],
            [
                'slug' => EventType::BRUNCH->value,
                'name' => EventType::BRUNCH->label(),
                'color' => EventType::BRUNCH->color(),
                'order' => 5,
            ],
            [
                'slug' => EventType::AUTRE->value,
                'name' => EventType::AUTRE->label(),
                'color' => EventType::AUTRE->color(),
                'order' => 6,
            ],
        ];

        // Create default types for all existing users
        User::chunk(100, function ($users) use ($defaultTypes) {
            foreach ($users as $user) {
                // Check if user already has event types
                if ($user->eventTypes()->count() === 0) {
                    foreach ($defaultTypes as $type) {
                        UserEventType::create([
                            'user_id' => $user->id,
                            'slug' => $type['slug'],
                            'name' => $type['name'],
                            'color' => $type['color'],
                            'is_default' => true,
                            'order' => $type['order'],
                        ]);
                    }
                }
            }
        });
    }
}
