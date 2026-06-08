<?php

namespace Database\Factories;

use App\Models\BudgetItem;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BudgetItemPayment>
 */
class BudgetItemPaymentFactory extends Factory
{
    public function definition(): array
    {
        $item = BudgetItem::factory()->create();

        return [
            'budget_item_id' => $item->id,
            'event_id' => $item->event_id,
            'created_by' => User::factory(),
            'amount' => fake()->numberBetween(10000, 250000),
            'payment_date' => fake()->date(),
            'method' => fake()->randomElement(['cash', 'mobile_money', 'bank_transfer', 'card', 'other']),
            'reference' => fake()->optional()->bothify('PAY-####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forItem(BudgetItem $item): static
    {
        return $this->state(fn () => [
            'budget_item_id' => $item->id,
            'event_id' => $item->event_id,
        ]);
    }
}
