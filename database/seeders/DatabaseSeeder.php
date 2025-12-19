<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Utilisateur admin
        User::firstOrCreate(
            ['email' => 'alexsonicka@gmail.com'],
            [
                'name' => 'Alexandre Sonicka',
                'password' => Hash::make('test1234'),
                'email_verified_at' => now(),
                'role' => UserRole::ADMIN,
            ]
        );

        // Utilisateurs de test
        User::firstOrCreate(
            ['email' => 'jane@example.com'],
            [
                'name' => 'Jane Doe',
                'password' => Hash::make('test1234'),
                'email_verified_at' => now(),
                'role' => UserRole::USER,
            ]
        );

        User::firstOrCreate(
            ['email' => 'john@example.com'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('test1234'),
                'email_verified_at' => now(),
                'role' => UserRole::USER,
            ]
        );

        // Seed event templates (required for app functionality)
        $this->call([
            EventTemplateSeeder::class,
        ]);

        // Comprehensive test data for development
        $this->call([
            ComprehensiveTestDataSeeder::class,
            AdminActivityLogSeeder::class,
        ]);
    }
}
