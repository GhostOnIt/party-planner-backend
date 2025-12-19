<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(EventType::cases());
        $date = fake()->dateTimeBetween('now', '+6 months');

        return [
            'user_id' => User::factory(),
            'title' => $this->getTitleForType($type),
            'type' => $type->value,
            'description' => fake()->optional(0.7)->paragraph(),
            'date' => $date,
            'time' => fake()->optional(0.8)->time('H:i'),
            'location' => fake()->optional(0.8)->address(),
            'estimated_budget' => fake()->optional(0.6)->numberBetween(50000, 5000000),
            'actual_budget' => null,
            'theme' => fake()->optional(0.5)->randomElement([
                'Bohème', 'Champêtre', 'Moderne', 'Vintage', 'Tropical',
                'Élégant', 'Rustique', 'Minimaliste', 'Glamour', 'Nature'
            ]),
            'expected_guests_count' => fake()->optional(0.7)->numberBetween(10, 300),
            'status' => fake()->randomElement(EventStatus::cases())->value,
        ];
    }

    /**
     * Get a title based on event type.
     */
    protected function getTitleForType(EventType $type): string
    {
        return match ($type) {
            EventType::MARIAGE => fake()->randomElement([
                'Mariage de ' . fake()->firstName() . ' & ' . fake()->firstName(),
                'Notre mariage',
                'Wedding ' . fake()->lastName(),
            ]),
            EventType::ANNIVERSAIRE => fake()->randomElement([
                'Anniversaire de ' . fake()->firstName(),
                fake()->numberBetween(18, 60) . ' ans de ' . fake()->firstName(),
                'Fête d\'anniversaire',
            ]),
            EventType::BABY_SHOWER => fake()->randomElement([
                'Baby Shower de ' . fake()->firstNameFemale(),
                'Bienvenue bébé ' . fake()->lastName(),
                'Baby Shower',
            ]),
            EventType::SOIREE => fake()->randomElement([
                'Soirée ' . fake()->word(),
                'Grande soirée',
                'Fête de fin d\'année',
                'Soirée d\'entreprise',
            ]),
            EventType::BRUNCH => fake()->randomElement([
                'Brunch dominical',
                'Brunch entre amis',
                'Brunch ' . fake()->monthName(),
            ]),
            EventType::AUTRE => fake()->randomElement([
                'Événement spécial',
                'Célébration',
                'Réunion familiale',
            ]),
        };
    }

    /**
     * Indicate that the event is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::DRAFT->value,
        ]);
    }

    /**
     * Indicate that the event is in planning.
     */
    public function planning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::PLANNING->value,
        ]);
    }

    /**
     * Indicate that the event is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::CONFIRMED->value,
        ]);
    }

    /**
     * Indicate that the event is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::COMPLETED->value,
            'date' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Indicate that the event is a wedding.
     */
    public function wedding(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EventType::MARIAGE->value,
            'title' => 'Mariage de ' . fake()->firstName() . ' & ' . fake()->firstName(),
            'expected_guests_count' => fake()->numberBetween(50, 300),
            'estimated_budget' => fake()->numberBetween(1000000, 10000000),
        ]);
    }

    /**
     * Indicate that the event is a birthday.
     */
    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EventType::ANNIVERSAIRE->value,
            'title' => 'Anniversaire de ' . fake()->firstName(),
            'expected_guests_count' => fake()->numberBetween(10, 100),
            'estimated_budget' => fake()->numberBetween(50000, 500000),
        ]);
    }
}
