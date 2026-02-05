<?php

namespace Database\Seeders;

use App\Models\Collaborator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MigrateExistingCollaboratorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // System roles are global (enum); no per-event system role rows

        // Migrate existing collaborators to new role system
        $collaborators = Collaborator::with('event')->get();

        foreach ($collaborators as $collaborator) {
            $this->migrateCollaboratorRole($collaborator);
        }

        $this->command->info('Migration des collaborateurs terminée.');
        $this->command->info($collaborators->count() . ' collaborateurs migrés.');
    }

    /**
     * Migrate a collaborator's role to the new system.
     * System roles are global (enum); we only set the role column.
     */
    private function migrateCollaboratorRole(Collaborator $collaborator): void
    {
        $roleMapping = [
            'owner' => 'owner',
            'editor' => 'coordinator',
            'viewer' => 'supervisor',
            'coordinator' => 'coordinator',
            'guest_manager' => 'guest_manager',
            'planner' => 'planner',
            'accountant' => 'accountant',
            'supervisor' => 'supervisor',
            'reporter' => 'reporter',
        ];

        $newRole = $roleMapping[$collaborator->role] ?? 'supervisor';
        $collaborator->update(['role' => $newRole]);
    }
}
