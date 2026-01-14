<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Events
            [
                'name' => 'events.view',
                'display_name' => 'Voir les événements',
                'description' => 'Permet de consulter les détails des événements',
                'module' => 'events',
                'action' => 'view',
            ],
            [
                'name' => 'events.edit',
                'display_name' => 'Modifier les événements',
                'description' => 'Permet de modifier les détails des événements (titre, date, lieu, description)',
                'module' => 'events',
                'action' => 'edit',
            ],

            // Guests
            [
                'name' => 'guests.view',
                'display_name' => 'Voir les invités',
                'description' => 'Permet de consulter la liste des invités',
                'module' => 'guests',
                'action' => 'view',
            ],
            [
                'name' => 'guests.create',
                'display_name' => 'Ajouter des invités',
                'description' => 'Permet d\'ajouter de nouveaux invités',
                'module' => 'guests',
                'action' => 'create',
            ],
            [
                'name' => 'guests.edit',
                'display_name' => 'Modifier les invités',
                'description' => 'Permet de modifier les informations des invités',
                'module' => 'guests',
                'action' => 'edit',
            ],
            [
                'name' => 'guests.delete',
                'display_name' => 'Supprimer des invités',
                'description' => 'Permet de supprimer des invités',
                'module' => 'guests',
                'action' => 'delete',
            ],
            [
                'name' => 'guests.import',
                'display_name' => 'Importer des invités',
                'description' => 'Permet d\'importer des invités depuis un fichier CSV',
                'module' => 'guests',
                'action' => 'import',
            ],
            [
                'name' => 'guests.export',
                'display_name' => 'Exporter les invités',
                'description' => 'Permet d\'exporter la liste des invités',
                'module' => 'guests',
                'action' => 'export',
            ],
            [
                'name' => 'guests.send_invitations',
                'display_name' => 'Envoyer les invitations',
                'description' => 'Permet d\'envoyer les invitations par email',
                'module' => 'guests',
                'action' => 'send_invitations',
            ],
            [
                'name' => 'guests.checkin',
                'display_name' => 'Check-in des invités',
                'description' => 'Permet de faire le check-in des invités sur place',
                'module' => 'guests',
                'action' => 'checkin',
            ],

            // Tasks
            [
                'name' => 'tasks.view',
                'display_name' => 'Voir les tâches',
                'description' => 'Permet de consulter la liste des tâches',
                'module' => 'tasks',
                'action' => 'view',
            ],
            [
                'name' => 'tasks.create',
                'display_name' => 'Créer des tâches',
                'description' => 'Permet de créer de nouvelles tâches',
                'module' => 'tasks',
                'action' => 'create',
            ],
            [
                'name' => 'tasks.edit',
                'display_name' => 'Modifier les tâches',
                'description' => 'Permet de modifier les tâches existantes',
                'module' => 'tasks',
                'action' => 'edit',
            ],
            [
                'name' => 'tasks.delete',
                'display_name' => 'Supprimer des tâches',
                'description' => 'Permet de supprimer des tâches',
                'module' => 'tasks',
                'action' => 'delete',
            ],
            [
                'name' => 'tasks.assign',
                'display_name' => 'Assigner des tâches',
                'description' => 'Permet d\'assigner des tâches à des collaborateurs',
                'module' => 'tasks',
                'action' => 'assign',
            ],
            [
                'name' => 'tasks.complete',
                'display_name' => 'Marquer comme terminé',
                'description' => 'Permet de marquer les tâches comme terminées',
                'module' => 'tasks',
                'action' => 'complete',
            ],

            // Budget
            [
                'name' => 'budget.view',
                'display_name' => 'Voir le budget',
                'description' => 'Permet de consulter le budget de l\'événement',
                'module' => 'budget',
                'action' => 'view',
            ],
            [
                'name' => 'budget.create',
                'display_name' => 'Ajouter des éléments budgétaires',
                'description' => 'Permet d\'ajouter de nouveaux éléments budgétaires',
                'module' => 'budget',
                'action' => 'create',
            ],
            [
                'name' => 'budget.edit',
                'display_name' => 'Modifier le budget',
                'description' => 'Permet de modifier les éléments budgétaires',
                'module' => 'budget',
                'action' => 'edit',
            ],
            [
                'name' => 'budget.delete',
                'display_name' => 'Supprimer des éléments budgétaires',
                'description' => 'Permet de supprimer des éléments budgétaires',
                'module' => 'budget',
                'action' => 'delete',
            ],
            [
                'name' => 'budget.export',
                'display_name' => 'Exporter le budget',
                'description' => 'Permet d\'exporter le budget en PDF',
                'module' => 'budget',
                'action' => 'export',
            ],

            // Photos
            [
                'name' => 'photos.view',
                'display_name' => 'Voir la galerie',
                'description' => 'Permet de consulter la galerie photos',
                'module' => 'photos',
                'action' => 'view',
            ],
            [
                'name' => 'photos.upload',
                'display_name' => 'Uploader des photos',
                'description' => 'Permet d\'ajouter de nouvelles photos',
                'module' => 'photos',
                'action' => 'upload',
            ],
            [
                'name' => 'photos.delete',
                'display_name' => 'Supprimer des photos',
                'description' => 'Permet de supprimer des photos',
                'module' => 'photos',
                'action' => 'delete',
            ],
            [
                'name' => 'photos.set_featured',
                'display_name' => 'Marquer comme principale',
                'description' => 'Permet de définir une photo comme photo principale',
                'module' => 'photos',
                'action' => 'set_featured',
            ],

            // Collaborators
            [
                'name' => 'collaborators.view',
                'display_name' => 'Voir les collaborateurs',
                'description' => 'Permet de consulter la liste des collaborateurs',
                'module' => 'collaborators',
                'action' => 'view',
            ],
            [
                'name' => 'collaborators.invite',
                'display_name' => 'Inviter des collaborateurs',
                'description' => 'Permet d\'inviter de nouveaux collaborateurs',
                'module' => 'collaborators',
                'action' => 'invite',
            ],
            [
                'name' => 'collaborators.edit_roles',
                'display_name' => 'Modifier les rôles',
                'description' => 'Permet de modifier les rôles des collaborateurs',
                'module' => 'collaborators',
                'action' => 'edit_roles',
            ],
            [
                'name' => 'collaborators.remove',
                'display_name' => 'Retirer des collaborateurs',
                'description' => 'Permet de retirer des collaborateurs de l\'événement',
                'module' => 'collaborators',
                'action' => 'remove',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}
