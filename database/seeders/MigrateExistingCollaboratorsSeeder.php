<?php

namespace Database\Seeders;

use App\Enums\CollaboratorRole;
use App\Models\Collaborator;
use App\Models\CustomRole;
use App\Models\Event;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MigrateExistingCollaboratorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create system roles for all existing events
        $events = Event::all();

        foreach ($events as $event) {
            $this->createSystemRolesForEvent($event);
        }

        // Migrate existing collaborators to new role system
        $collaborators = Collaborator::with('event')->get();

        foreach ($collaborators as $collaborator) {
            $this->migrateCollaboratorRole($collaborator);
        }

        $this->command->info('Migration des collaborateurs terminée.');
        $this->command->info('Rôles système créés pour ' . $events->count() . ' événements.');
        $this->command->info($collaborators->count() . ' collaborateurs migrés.');
    }

    /**
     * Create system roles for an event.
     */
    private function createSystemRolesForEvent(Event $event): void
    {
        $systemRoles = [
            [
                'name' => 'Propriétaire',
                'description' => 'Accès complet, peut supprimer l\'événement',
                'color' => 'yellow',
                'role_enum' => CollaboratorRole::OWNER,
            ],
            [
                'name' => 'Coordinateur',
                'description' => 'Peut modifier tous les aspects de l\'événement',
                'color' => 'purple',
                'role_enum' => CollaboratorRole::COORDINATOR,
            ],
            [
                'name' => 'Gestionnaire d\'Invités',
                'description' => 'Gestion complète des invités',
                'color' => 'blue',
                'role_enum' => CollaboratorRole::GUEST_MANAGER,
            ],
            [
                'name' => 'Planificateur',
                'description' => 'Gestion des tâches et planning',
                'color' => 'green',
                'role_enum' => CollaboratorRole::PLANNER,
            ],
            [
                'name' => 'Comptable',
                'description' => 'Gestion du budget et finances',
                'color' => 'indigo',
                'role_enum' => CollaboratorRole::ACCOUNTANT,
            ],
            [
                'name' => 'Photographe',
                'description' => 'Gestion de la galerie photo',
                'color' => 'pink',
                'role_enum' => CollaboratorRole::PHOTOGRAPHER,
            ],
            [
                'name' => 'Superviseur',
                'description' => 'Accès en lecture seule sur tout',
                'color' => 'gray',
                'role_enum' => CollaboratorRole::SUPERVISOR,
            ],
            [
                'name' => 'Rapporteur',
                'description' => 'Accès aux rapports et exports',
                'color' => 'orange',
                'role_enum' => CollaboratorRole::REPORTER,
            ],
        ];

        foreach ($systemRoles as $roleData) {
            // Check if role already exists
            $existingRole = CustomRole::where('event_id', $event->id)
                ->where('name', $roleData['name'])
                ->where('is_system', true)
                ->first();

            if (!$existingRole) {
                CustomRole::create([
                    'event_id' => $event->id,
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'color' => $roleData['color'],
                    'is_system' => true,
                    'created_by' => $event->user_id,
                ]);
            }
        }
    }

    /**
     * Migrate a collaborator's role to the new system.
     */
    private function migrateCollaboratorRole(Collaborator $collaborator): void
    {
        // Skip if already migrated (has custom_role_id)
        if ($collaborator->custom_role_id) {
            return;
        }

        $roleMapping = [
            'owner' => 'Propriétaire',
            'editor' => 'Coordinateur', // Migrate old editor to coordinator
            'viewer' => 'Superviseur', // Migrate old viewer to supervisor
            'coordinator' => 'Coordinateur',
            'guest_manager' => 'Gestionnaire d\'Invités',
            'planner' => 'Planificateur',
            'accountant' => 'Comptable',
            'photographer' => 'Photographe',
            'supervisor' => 'Superviseur',
            'reporter' => 'Rapporteur',
        ];

        $newRoleName = $roleMapping[$collaborator->role] ?? 'Superviseur'; // Default to supervisor

        // Find the corresponding custom role
        $customRole = CustomRole::where('event_id', $collaborator->event_id)
            ->where('name', $newRoleName)
            ->where('is_system', true)
            ->first();

        if ($customRole) {
            $collaborator->update(['custom_role_id' => $customRole->id]);
        }
    }
}
