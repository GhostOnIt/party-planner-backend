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
            // Gratuit - acquisition
            [
                'name' => 'Gratuit',
                'title' => 'Plan Gratuit - Découvrir la plateforme',
                'slug' => 'gratuit',
                'description' => 'Acquisition. Faire découvrir le produit.',
                'price' => 0,
                'duration_days' => 30,
                'is_trial' => false,
                'is_one_time_use' => false,
                'is_active' => true,
                'sort_order' => 0,
                'limits' => [
                    'events.creations_per_billing_period' => 2,
                    'guests.max_per_event' => 30,
                    'collaborators.max_per_event' => 0,
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
                    'collaborators.manage' => false,
                    'roles_permissions.enabled' => false,
                    'exports.pdf' => true,
                    'exports.excel' => false,
                    'exports.csv' => false,
                    'history.enabled' => false,
                    'reporting.enabled' => false,
                    'branding.custom' => false,
                    'support.whatsapp_priority' => false,
                    'multi_client.enabled' => false,
                    'checkin.tablet' => false,
                ],
            ],

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

            // Starter - 3 500 FCFA / mois
            [
                'name' => 'Starter',
                'title' => 'Plan Starter - Pour particuliers',
                'slug' => 'starter',
                'description' => 'Particuliers organisant des événements perso.',
                'price' => 3500,
                'duration_days' => 30,
                'is_trial' => false,
                'is_active' => true,
                'sort_order' => 2,
                'limits' => [
                    'events.creations_per_billing_period' => 10,
                    'guests.max_per_event' => 150,
                    'collaborators.max_per_event' => 2,
                    'photos.max_per_event' => 50,
                ],
                'features' => [
                    'budget.enabled' => true,
                    'tasks.enabled' => true,
                    'guests.manage' => true,
                    'guests.import' => true,
                    'guests.export' => false,
                    'invitations.sms' => false,
                    'invitations.whatsapp' => false,
                    'collaborators.manage' => true,
                    'roles_permissions.enabled' => false,
                    'exports.pdf' => true,
                    'exports.excel' => false,
                    'exports.csv' => false,
                    'history.enabled' => true,
                    'reporting.enabled' => false,
                    'branding.custom' => false,
                    'support.whatsapp_priority' => false,
                    'multi_client.enabled' => false,
                    'checkin.tablet' => false,
                ],
            ],

            // PRO - 9 900 FCFA / mois
            [
                'name' => 'PRO',
                'title' => 'Plan PRO - Pour organisateurs indépendants & freelances',
                'slug' => 'pro',
                'description' => 'Pour organisateurs indépendants & freelances',
                'price' => 9900,
                'duration_days' => 30,
                'is_trial' => false,
                'is_active' => true,
                'sort_order' => 3,
                'limits' => [
                    'events.creations_per_billing_period' => -1,
                    'guests.max_per_event' => 500,
                    'collaborators.max_per_event' => 10,
                    'photos.max_per_event' => 500,
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
                    'checkin.tablet' => true,
                ],
            ],

            // Business - sur devis (pas de paiement self-service)
            [
                'name' => 'Business',
                'title' => 'Plan Business - Sur devis',
                'slug' => 'business',
                'description' => 'Pour entreprises, agences et organisations avancées.',
                'price' => 0,
                'duration_days' => 30,
                'is_trial' => false,
                'is_active' => true,
                'sort_order' => 4,
                'limits' => [
                    'events.creations_per_billing_period' => -1,
                    'guests.max_per_event' => -1,
                    'collaborators.max_per_event' => -1,
                    'photos.max_per_event' => -1,
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
                    'checkin.tablet' => true,
                    'sales.contact_required' => true,
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

