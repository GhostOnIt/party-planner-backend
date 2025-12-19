<?php

namespace Database\Factories;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventTemplate>
 */
class EventTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventType = fake()->randomElement(EventType::cases());

        return [
            'event_type' => $eventType->value,
            'name' => 'Template ' . $eventType->label(),
            'description' => fake()->paragraph(),
            'default_tasks' => $this->getDefaultTasks($eventType),
            'default_budget_categories' => $this->getDefaultBudgetCategories($eventType),
            'suggested_themes' => $this->getSuggestedThemes($eventType),
            'is_active' => true,
        ];
    }

    /**
     * Get default tasks for event type.
     */
    protected function getDefaultTasks(EventType $type): array
    {
        $commonTasks = [
            ['title' => 'Définir le budget', 'priority' => 'high'],
            ['title' => 'Choisir la date', 'priority' => 'high'],
            ['title' => 'Réserver le lieu', 'priority' => 'high'],
            ['title' => 'Créer la liste d\'invités', 'priority' => 'medium'],
            ['title' => 'Envoyer les invitations', 'priority' => 'medium'],
        ];

        $specificTasks = match ($type) {
            EventType::MARIAGE => [
                ['title' => 'Choisir le traiteur', 'priority' => 'high'],
                ['title' => 'Réserver le photographe', 'priority' => 'high'],
                ['title' => 'Commander le gâteau', 'priority' => 'medium'],
                ['title' => 'Organiser la cérémonie', 'priority' => 'high'],
                ['title' => 'Choisir les alliances', 'priority' => 'high'],
                ['title' => 'Planifier la lune de miel', 'priority' => 'low'],
            ],
            EventType::ANNIVERSAIRE => [
                ['title' => 'Commander le gâteau', 'priority' => 'high'],
                ['title' => 'Préparer la décoration', 'priority' => 'medium'],
                ['title' => 'Organiser les jeux/animations', 'priority' => 'medium'],
                ['title' => 'Prévoir la musique', 'priority' => 'low'],
            ],
            EventType::BABY_SHOWER => [
                ['title' => 'Choisir le thème', 'priority' => 'high'],
                ['title' => 'Préparer les jeux', 'priority' => 'medium'],
                ['title' => 'Commander le gâteau', 'priority' => 'medium'],
                ['title' => 'Organiser les cadeaux', 'priority' => 'medium'],
            ],
            default => [
                ['title' => 'Planifier le menu', 'priority' => 'medium'],
                ['title' => 'Organiser la décoration', 'priority' => 'medium'],
            ],
        };

        return array_merge($commonTasks, $specificTasks);
    }

    /**
     * Get default budget categories for event type.
     */
    protected function getDefaultBudgetCategories(EventType $type): array
    {
        return match ($type) {
            EventType::MARIAGE => [
                ['category' => 'location', 'name' => 'Salle de réception'],
                ['category' => 'catering', 'name' => 'Traiteur'],
                ['category' => 'photography', 'name' => 'Photographe/Vidéaste'],
                ['category' => 'decoration', 'name' => 'Fleurs et décoration'],
                ['category' => 'entertainment', 'name' => 'Musique/DJ'],
                ['category' => 'transportation', 'name' => 'Transport'],
                ['category' => 'other', 'name' => 'Alliances'],
                ['category' => 'other', 'name' => 'Tenue mariés'],
            ],
            EventType::ANNIVERSAIRE => [
                ['category' => 'location', 'name' => 'Location salle'],
                ['category' => 'catering', 'name' => 'Nourriture et boissons'],
                ['category' => 'catering', 'name' => 'Gâteau'],
                ['category' => 'decoration', 'name' => 'Décoration'],
                ['category' => 'entertainment', 'name' => 'Animation'],
            ],
            default => [
                ['category' => 'location', 'name' => 'Lieu'],
                ['category' => 'catering', 'name' => 'Restauration'],
                ['category' => 'decoration', 'name' => 'Décoration'],
            ],
        };
    }

    /**
     * Get suggested themes for event type.
     */
    protected function getSuggestedThemes(EventType $type): array
    {
        return match ($type) {
            EventType::MARIAGE => ['Bohème', 'Champêtre', 'Romantique', 'Moderne', 'Vintage', 'Élégant', 'Tropical'],
            EventType::ANNIVERSAIRE => ['Super-héros', 'Princesse', 'Safari', 'Espace', 'Disco', 'Tropical', 'Rétro'],
            EventType::BABY_SHOWER => ['Nuages', 'Safari', 'Étoiles', 'Animaux', 'Arc-en-ciel', 'Jungle'],
            EventType::SOIREE => ['Gatsby', 'Hollywood', 'Casino', 'Mascarade', 'Tropical', 'Blanc'],
            EventType::BRUNCH => ['Garden Party', 'Champêtre', 'Provençal', 'Minimaliste'],
            default => ['Élégant', 'Moderne', 'Rustique', 'Coloré'],
        };
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template is for a specific event type.
     */
    public function forType(EventType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $type->value,
            'name' => 'Template ' . $type->label(),
            'default_tasks' => $this->getDefaultTasks($type),
            'default_budget_categories' => $this->getDefaultBudgetCategories($type),
            'suggested_themes' => $this->getSuggestedThemes($type),
        ]);
    }
}
