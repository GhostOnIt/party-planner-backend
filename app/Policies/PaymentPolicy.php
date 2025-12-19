<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Perform pre-authorization checks.
     * Admins can do anything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $payment->subscription->user_id === $user->id;
    }

    /**
     * Determine whether the user can create a payment.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can initiate a payment.
     */
    public function initiate(User $user, Payment $payment): bool
    {
        return $payment->subscription->user_id === $user->id;
    }

    /**
     * Determine whether the user can check payment status.
     */
    public function checkStatus(User $user, Payment $payment): bool
    {
        return $payment->subscription->user_id === $user->id;
    }

    /**
     * Determine whether the user can retry a failed payment.
     */
    public function retry(User $user, Payment $payment): bool
    {
        if ($payment->subscription->user_id !== $user->id) {
            return false;
        }

        return $payment->isFailed();
    }

    /**
     * Determine whether the user can request a refund.
     */
    public function requestRefund(User $user, Payment $payment): bool
    {
        if ($payment->subscription->user_id !== $user->id) {
            return false;
        }

        return $payment->isCompleted();
    }

    /**
     * Determine whether the user can cancel a pending payment.
     */
    public function cancel(User $user, Payment $payment): bool
    {
        if ($payment->subscription->user_id !== $user->id) {
            return false;
        }

        return $payment->isPending();
    }
}
