<?php

namespace Database\Factories;

use App\Enums\BudgetCategory;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BudgetItem>
 */
class BudgetItemFactory extends Factory
{
    /**
     * Budget item templates by category.
     */
    protected array $itemTemplates = [
        'location' => ['Salle de réception', 'Location terrain', 'Frais de réservation', 'Caution'],
        'catering' => ['Menu entrée', 'Plat principal', 'Desserts', 'Boissons', 'Cocktail', 'Service'],
        'decoration' => ['Fleurs', 'Ballons', 'Nappes et serviettes', 'Centre de table', 'Éclairage'],
        'entertainment' => ['DJ', 'Groupe de musique', 'Animation enfants', 'Photobooth', 'Feu d\'artifice'],
        'photography' => ['Photographe', 'Vidéaste', 'Album photo', 'Tirages'],
        'transportation' => ['Location voiture', 'Bus invités', 'Parking', 'Navette'],
        'other' => ['Cadeaux invités', 'Assurance', 'Pourboires', 'Imprévus'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(BudgetCategory::cases());
        $estimatedCost = fake()->numberBetween(10000, 500000);
        $hasActualCost = fake()->boolean(60);
        $variance = fake()->numberBetween(-20, 30);
        $actualCost = $hasActualCost ? $estimatedCost * (1 + $variance / 100) : null;
        $paid = $hasActualCost && fake()->boolean(70);

        return [
            'event_id' => Event::factory(),
            'category' => $category->value,
            'name' => fake()->randomElement($this->itemTemplates[$category->value]),
            'estimated_cost' => $estimatedCost,
            'actual_cost' => $actualCost,
            'paid' => $paid,
            'payment_date' => $paid ? fake()->dateTimeBetween('-2 months', 'now') : null,
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    /**
     * Indicate that the item is for a specific category.
     */
    public function category(BudgetCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category->value,
            'name' => fake()->randomElement($this->itemTemplates[$category->value]),
        ]);
    }

    /**
     * Indicate that the item is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid' => true,
            'payment_date' => fake()->dateTimeBetween('-2 months', 'now'),
            'actual_cost' => $attributes['estimated_cost'] ?? fake()->numberBetween(10000, 500000),
        ]);
    }

    /**
     * Indicate that the item is unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid' => false,
            'payment_date' => null,
        ]);
    }

    /**
     * Indicate that the item is over budget.
     */
    public function overBudget(): static
    {
        return $this->state(function (array $attributes) {
            $estimated = $attributes['estimated_cost'] ?? fake()->numberBetween(10000, 500000);
            return [
                'estimated_cost' => $estimated,
                'actual_cost' => $estimated * fake()->randomFloat(2, 1.2, 1.5),
            ];
        });
    }
}
