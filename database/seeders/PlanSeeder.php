<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            // Essai Gratuit - 14 jours
            [
                'name' => 'Essai Gratuit',
                'title' => 'Essayez Party Planner Pro gratuitement pendant 14 jours',
                'slug' => 'essai-gratuit',
                'description' => 'Découvrez Party Planner sans engagement',
                'price' => 0,
                'duration_days' => 14,
                'is_trial' => true,
                'is_one_time_use' => true,
                'is_active' => true,
                'sort_order' => 1,
                'limits' => [
                    'events.creations_per_billing_period' => 1,
                    'guests.max_per_event' => 100,
                    'collaborators.max_per_event' => 1,
                    'photos.max_per_event' => 10,
                ],
                'features' => [
                    'budget.enabled' => true,
                    'tasks.enabled' => true,
                    'guests.manage' => true,
                    'guests.import' => false,
                    'guests.export' => false,
                    'invitations.sms' => false,
                    'invitations.whatsapp' => false,
                    'collaborators.manage' => true,
                    'roles_permissions.enabled' => false,
                    'exports.pdf' => false,
                    'exports.excel' => false,
                    'exports.csv' => false,
                    'history.enabled' => false,
                    'reporting.enabled' => false,
                    'branding.custom' => false,
                    'support.whatsapp_priority' => false,
                    'multi_client.enabled' => false,
                ],
            ],

            // PRO - 10 000 FCFA / mois
            [
                'name' => 'PRO',
                'title' => 'Plan PRO - Pour organisateurs indépendants & freelances',
                'slug' => 'pro',
                'description' => 'Pour organisateurs indépendants & freelances',
                'price' => 10000,
                'duration_days' => 30,
                'is_trial' => false,
                'is_active' => true,
                'sort_order' => 2,
                'limits' => [
                    'events.creations_per_billing_period' => 200,
                    'guests.max_per_event' => -1, // Illimité
                    'collaborators.max_per_event' => -1, // Illimité
                    'photos.max_per_event' => -1, // Illimité
                ],
                'features' => [
                    'budget.enabled' => true,
                    'tasks.enabled' => true,
                    'guests.manage' => true,
                    'guests.import' => true,
                    'guests.export' => true,
                    'invitations.sms' => false,
                    'invitations.whatsapp' => false,
                    'collaborators.manage' => true,
                    'roles_permissions.enabled' => true,
                    'exports.pdf' => true,
                    'exports.excel' => true,
                    'exports.csv' => true,
                    'history.enabled' => true,
                    'reporting.enabled' => true,
                    'branding.custom' => false,
                    'support.whatsapp_priority' => true,
                    'multi_client.enabled' => false,
                ],
            ],

            // AGENCE - 25 000 FCFA / mois
            [
                'name' => 'AGENCE',
                'title' => 'Plan AGENCE - Pour agences, églises, ONG, entreprises',
                'slug' => 'agence',
                'description' => 'Pour agences, églises, ONG, entreprises',
                'price' => 25000,
                'duration_days' => 30,
                'is_trial' => false,
                'is_active' => true,
                'sort_order' => 3,
                'limits' => [
                    'events.creations_per_billing_period' => 500,
                    'guests.max_per_event' => -1, // Illimité
                    'collaborators.max_per_event' => -1, // Illimité
                    'photos.max_per_event' => -1, // Illimité
                ],
                'features' => [
                    'budget.enabled' => true,
                    'tasks.enabled' => true,
                    'guests.manage' => true,
                    'guests.import' => true,
                    'guests.export' => true,
                    'invitations.sms' => true,
                    'invitations.whatsapp' => true,
                    'collaborators.manage' => true,
                    'roles_permissions.enabled' => true,
                    'exports.pdf' => true,
                    'exports.excel' => true,
                    'exports.csv' => true,
                    'history.enabled' => true,
                    'reporting.enabled' => true,
                    'branding.custom' => true,
                    'support.whatsapp_priority' => true,
                    'multi_client.enabled' => true,
                ],
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}

