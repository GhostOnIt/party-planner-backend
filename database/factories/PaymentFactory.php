<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentMethod = fake()->randomElement(PaymentMethod::cases());
        $status = fake()->randomElement(PaymentStatus::cases());

        return [
            'subscription_id' => Subscription::factory(),
            'amount' => fake()->numberBetween(5000, 50000),
            'currency' => 'XAF',
            'payment_method' => $paymentMethod->value,
            'transaction_reference' => $status === PaymentStatus::COMPLETED
                ? strtoupper($paymentMethod->name) . '-' . fake()->uuid()
                : null,
            'status' => $status->value,
            'metadata' => [
                'phone' => fake()->numerify('6########'),
                'initiated_at' => fake()->dateTimeBetween('-1 month', 'now')->format('c'),
            ],
        ];
    }

    /**
     * Indicate that the payment is via MTN Mobile Money.
     */
    public function mtn(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::MTN_MOBILE_MONEY->value,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'phone' => fake()->numerify('67#######'),
            ]),
        ]);
    }

    /**
     * Indicate that the payment is via Airtel Money.
     */
    public function airtel(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::AIRTEL_MONEY->value,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'phone' => fake()->numerify('69#######'),
            ]),
        ]);
    }

    /**
     * Indicate that the payment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::COMPLETED->value,
            'transaction_reference' => 'TXN-' . fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PENDING->value,
            'transaction_reference' => null,
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED->value,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'error' => 'Insufficient funds',
            ]),
        ]);
    }
}
