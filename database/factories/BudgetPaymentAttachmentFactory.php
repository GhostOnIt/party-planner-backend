<?php

namespace Database\Factories;

use App\Models\BudgetItemPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BudgetPaymentAttachment>
 */
class BudgetPaymentAttachmentFactory extends Factory
{
    public function definition(): array
    {
        $payment = BudgetItemPayment::factory()->create();

        return [
            'budget_item_payment_id' => $payment->id,
            'budget_item_id' => $payment->budget_item_id,
            'event_id' => $payment->event_id,
            'uploaded_by' => User::factory(),
            'original_name' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12345,
            's3_path' => "events/{$payment->event_id}/budget/{$payment->budget_item_id}/payments/{$payment->id}/receipt.pdf",
        ];
    }

    public function forPayment(BudgetItemPayment $payment): static
    {
        return $this->state(fn () => [
            'budget_item_payment_id' => $payment->id,
            'budget_item_id' => $payment->budget_item_id,
            'event_id' => $payment->event_id,
        ]);
    }
}
