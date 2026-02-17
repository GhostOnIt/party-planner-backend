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
     *
     * Only seeds essential data: Users (admins), Permissions, and Plans.
     * All other data (events, guests, etc.) can be created via the platform.
     */
    public function run(): void
    {
        // Admin users
        User::firstOrCreate(
            ['email' => 'pamarolic017@gmail.com'],
            [
                'name' => 'Rolic PAMA',
                'password' => Hash::make('Test@1234'),
                'email_verified_at' => now(),
                'role' => UserRole::ADMIN,
            ]
        );

        User::firstOrCreate(
            ['email' => 'alexsonicka@gmail.com'],
            [
                'name' => 'Alexandre Sonicka',
                'password' => Hash::make('Test@1234'),
                'email_verified_at' => now(),
                'role' => UserRole::ADMIN,
            ]
        );

        // Seed permissions and plans (essential for app functionality)
        $this->call([
            PermissionSeeder::class,
            PlanSeeder::class,
            UserEventTypesSeeder::class,
            UserCollaboratorRolesSeeder::class,
            UserBudgetCategoriesSeeder::class,
            CommunicationSpotSeeder::class,
            LegalPageSeeder::class,
        ]);

        // Optional: Activity logs test data (run separately with: php artisan db:seed --class=ActivityLogSeeder)
        // $this->call([ActivityLogSeeder::class]);
    }
}
