<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Models\EventTemplate;
use Illuminate\Database\Seeder;

class EventTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'event_type' => EventType::MARIAGE->value,
                'name' => 'Mariage classique',
                'description' => 'Template complet pour organiser un mariage traditionnel avec toutes les étapes essentielles.',
                'default_tasks' => [
                    ['title' => 'Définir le budget global', 'priority' => 'high'],
                    ['title' => 'Choisir la date', 'priority' => 'high'],
                    ['title' => 'Réserver le lieu de cérémonie', 'priority' => 'high'],
                    ['title' => 'Réserver le lieu de réception', 'priority' => 'high'],
                    ['title' => 'Choisir le traiteur', 'priority' => 'high'],
                    ['title' => 'Réserver le photographe', 'priority' => 'high'],
                    ['title' => 'Réserver le vidéaste', 'priority' => 'medium'],
                    ['title' => 'Créer la liste d\'invités', 'priority' => 'high'],
                    ['title' => 'Envoyer les faire-part', 'priority' => 'medium'],
                    ['title' => 'Choisir les alliances', 'priority' => 'high'],
                    ['title' => 'Commander le gâteau', 'priority' => 'medium'],
                    ['title' => 'Organiser la décoration florale', 'priority' => 'medium'],
                    ['title' => 'Réserver le DJ/groupe', 'priority' => 'medium'],
                    ['title' => 'Planifier le plan de table', 'priority' => 'medium'],
                    ['title' => 'Organiser le transport', 'priority' => 'low'],
                    ['title' => 'Préparer les cadeaux invités', 'priority' => 'low'],
                ],
                'default_budget_categories' => [
                    ['category' => 'location', 'name' => 'Lieu de cérémonie'],
                    ['category' => 'location', 'name' => 'Lieu de réception'],
                    ['category' => 'catering', 'name' => 'Traiteur - Menu'],
                    ['category' => 'catering', 'name' => 'Boissons'],
                    ['category' => 'catering', 'name' => 'Gâteau de mariage'],
                    ['category' => 'decoration', 'name' => 'Fleurs et compositions'],
                    ['category' => 'decoration', 'name' => 'Décoration salle'],
                    ['category' => 'photography', 'name' => 'Photographe'],
                    ['category' => 'photography', 'name' => 'Vidéaste'],
                    ['category' => 'entertainment', 'name' => 'DJ / Groupe'],
                    ['category' => 'transportation', 'name' => 'Voiture des mariés'],
                    ['category' => 'other', 'name' => 'Alliances'],
                    ['category' => 'other', 'name' => 'Tenues des mariés'],
                ],
                'suggested_themes' => ['Romantique', 'Bohème', 'Champêtre', 'Élégant', 'Vintage', 'Moderne', 'Tropical', 'Rustique'],
            ],
            [
                'event_type' => EventType::ANNIVERSAIRE->value,
                'name' => 'Anniversaire adulte',
                'description' => 'Template pour organiser une fête d\'anniversaire mémorable.',
                'default_tasks' => [
                    ['title' => 'Définir le budget', 'priority' => 'high'],
                    ['title' => 'Choisir le thème', 'priority' => 'high'],
                    ['title' => 'Réserver le lieu', 'priority' => 'high'],
                    ['title' => 'Créer la liste d\'invités', 'priority' => 'high'],
                    ['title' => 'Envoyer les invitations', 'priority' => 'medium'],
                    ['title' => 'Commander le gâteau', 'priority' => 'high'],
                    ['title' => 'Planifier le menu/buffet', 'priority' => 'medium'],
                    ['title' => 'Organiser la décoration', 'priority' => 'medium'],
                    ['title' => 'Prévoir la musique', 'priority' => 'medium'],
                    ['title' => 'Planifier les animations', 'priority' => 'low'],
                ],
                'default_budget_categories' => [
                    ['category' => 'location', 'name' => 'Location salle'],
                    ['category' => 'catering', 'name' => 'Nourriture'],
                    ['category' => 'catering', 'name' => 'Boissons'],
                    ['category' => 'catering', 'name' => 'Gâteau'],
                    ['category' => 'decoration', 'name' => 'Décoration'],
                    ['category' => 'decoration', 'name' => 'Ballons'],
                    ['category' => 'entertainment', 'name' => 'Musique/DJ'],
                ],
                'suggested_themes' => ['Disco', 'Années 80', 'Tropical', 'Casino', 'Élégant', 'Surprise', 'Coloré'],
            ],
            [
                'event_type' => EventType::BABY_SHOWER->value,
                'name' => 'Baby Shower',
                'description' => 'Template pour célébrer l\'arrivée de bébé.',
                'default_tasks' => [
                    ['title' => 'Choisir la date', 'priority' => 'high'],
                    ['title' => 'Définir le thème', 'priority' => 'high'],
                    ['title' => 'Créer la liste d\'invités', 'priority' => 'high'],
                    ['title' => 'Envoyer les invitations', 'priority' => 'medium'],
                    ['title' => 'Commander le gâteau', 'priority' => 'medium'],
                    ['title' => 'Préparer les jeux', 'priority' => 'medium'],
                    ['title' => 'Organiser la décoration', 'priority' => 'medium'],
                    ['title' => 'Prévoir les cadeaux/tombola', 'priority' => 'low'],
                    ['title' => 'Créer la liste de naissance', 'priority' => 'medium'],
                ],
                'default_budget_categories' => [
                    ['category' => 'location', 'name' => 'Lieu'],
                    ['category' => 'catering', 'name' => 'Buffet sucré/salé'],
                    ['category' => 'catering', 'name' => 'Gâteau'],
                    ['category' => 'catering', 'name' => 'Boissons'],
                    ['category' => 'decoration', 'name' => 'Décoration thème'],
                    ['category' => 'decoration', 'name' => 'Ballons'],
                    ['category' => 'entertainment', 'name' => 'Jeux et animations'],
                    ['category' => 'other', 'name' => 'Cadeaux invités'],
                ],
                'suggested_themes' => ['Nuages', 'Safari', 'Étoiles', 'Arc-en-ciel', 'Jungle', 'Animaux mignons', 'Princesse/Prince'],
            ],
            [
                'event_type' => EventType::SOIREE->value,
                'name' => 'Soirée privée',
                'description' => 'Template pour organiser une soirée privée réussie.',
                'default_tasks' => [
                    ['title' => 'Définir le budget', 'priority' => 'high'],
                    ['title' => 'Choisir le lieu', 'priority' => 'high'],
                    ['title' => 'Définir le dress code', 'priority' => 'medium'],
                    ['title' => 'Créer la liste d\'invités', 'priority' => 'high'],
                    ['title' => 'Envoyer les invitations', 'priority' => 'medium'],
                    ['title' => 'Organiser le catering', 'priority' => 'medium'],
                    ['title' => 'Réserver le DJ', 'priority' => 'medium'],
                    ['title' => 'Planifier la décoration', 'priority' => 'low'],
                ],
                'default_budget_categories' => [
                    ['category' => 'location', 'name' => 'Location lieu'],
                    ['category' => 'catering', 'name' => 'Cocktails/Boissons'],
                    ['category' => 'catering', 'name' => 'Amuse-bouches'],
                    ['category' => 'entertainment', 'name' => 'DJ'],
                    ['category' => 'decoration', 'name' => 'Décoration et éclairage'],
                ],
                'suggested_themes' => ['Gatsby', 'Mascarade', 'All White', 'Hollywood', 'Casino', 'Tropical', 'Neon'],
            ],
            [
                'event_type' => EventType::BRUNCH->value,
                'name' => 'Brunch convivial',
                'description' => 'Template pour un brunch entre amis ou en famille.',
                'default_tasks' => [
                    ['title' => 'Choisir le lieu', 'priority' => 'high'],
                    ['title' => 'Définir le menu', 'priority' => 'high'],
                    ['title' => 'Inviter les convives', 'priority' => 'medium'],
                    ['title' => 'Prévoir les boissons', 'priority' => 'medium'],
                    ['title' => 'Organiser la décoration de table', 'priority' => 'low'],
                ],
                'default_budget_categories' => [
                    ['category' => 'location', 'name' => 'Lieu (si extérieur)'],
                    ['category' => 'catering', 'name' => 'Nourriture brunch'],
                    ['category' => 'catering', 'name' => 'Boissons (jus, café, thé)'],
                    ['category' => 'decoration', 'name' => 'Décoration de table'],
                ],
                'suggested_themes' => ['Garden Party', 'Champêtre', 'Provençal', 'Minimaliste', 'Fleuri'],
            ],
            [
                'event_type' => EventType::AUTRE->value,
                'name' => 'Événement personnalisé',
                'description' => 'Template de base pour tout type d\'événement.',
                'default_tasks' => [
                    ['title' => 'Définir les objectifs', 'priority' => 'high'],
                    ['title' => 'Établir le budget', 'priority' => 'high'],
                    ['title' => 'Choisir la date et le lieu', 'priority' => 'high'],
                    ['title' => 'Créer la liste des participants', 'priority' => 'medium'],
                    ['title' => 'Envoyer les invitations', 'priority' => 'medium'],
                    ['title' => 'Organiser la logistique', 'priority' => 'medium'],
                ],
                'default_budget_categories' => [
                    ['category' => 'location', 'name' => 'Lieu'],
                    ['category' => 'catering', 'name' => 'Restauration'],
                    ['category' => 'decoration', 'name' => 'Décoration'],
                    ['category' => 'other', 'name' => 'Divers'],
                ],
                'suggested_themes' => ['Élégant', 'Moderne', 'Décontracté', 'Thématique'],
            ],
        ];

        foreach ($templates as $template) {
            EventTemplate::firstOrCreate(
                [
                    'event_type' => $template['event_type'],
                    'name' => $template['name'],
                ],
                $template
            );
        }
    }
}
