<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PlanType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $planType = fake()->randomElement(PlanType::cases());
        $guestCount = fake()->numberBetween(10, 250);
        $extraGuests = max(0, $guestCount - $planType->includedGuests());
        $guestPrice = $extraGuests * $planType->pricePerExtraGuest();
        $totalPrice = $planType->basePrice() + $guestPrice;

        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'plan_type' => $planType->value,
            'base_price' => $planType->basePrice(),
            'guest_count' => $guestCount,
            'guest_price_per_unit' => $planType->pricePerExtraGuest(),
            'total_price' => $totalPrice,
            'payment_status' => fake()->randomElement(PaymentStatus::cases())->value,
            'payment_method' => fake()->optional(0.7)->randomElement(['mtn_mobile_money', 'airtel_money']),
            'payment_reference' => fake()->optional(0.5)->uuid(),
            'expires_at' => fake()->optional(0.8)->dateTimeBetween('now', '+1 year'),
        ];
    }

    /**
     * Indicate that the subscription is for the starter plan.
     */
    public function starter(): static
    {
        $planType = PlanType::STARTER;

        return $this->state(function (array $attributes) use ($planType) {
            $guestCount = $attributes['guest_count'] ?? fake()->numberBetween(10, 100);
            $extraGuests = max(0, $guestCount - $planType->includedGuests());
            $guestPrice = $extraGuests * $planType->pricePerExtraGuest();

            return [
                'plan_type' => $planType->value,
                'base_price' => $planType->basePrice(),
                'guest_price_per_unit' => $planType->pricePerExtraGuest(),
                'total_price' => $planType->basePrice() + $guestPrice,
            ];
        });
    }

    /**
     * Indicate that the subscription is for the pro plan.
     */
    public function pro(): static
    {
        $planType = PlanType::PRO;

        return $this->state(function (array $attributes) use ($planType) {
            $guestCount = $attributes['guest_count'] ?? fake()->numberBetween(50, 300);
            $extraGuests = max(0, $guestCount - $planType->includedGuests());
            $guestPrice = $extraGuests * $planType->pricePerExtraGuest();

            return [
                'plan_type' => $planType->value,
                'base_price' => $planType->basePrice(),
                'guest_price_per_unit' => $planType->pricePerExtraGuest(),
                'total_price' => $planType->basePrice() + $guestPrice,
            ];
        });
    }

    /**
     * Indicate that the subscription is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'expires_at' => fake()->dateTimeBetween('+6 months', '+1 year'),
        ]);
    }

    /**
     * Indicate that the subscription is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'expires_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }
}
