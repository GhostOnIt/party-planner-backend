<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\User;

class QuoteRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, QuoteRequest $quoteRequest): bool
    {
        return $quoteRequest->user_id === $user->id;
    }

    public function update(User $user, QuoteRequest $quoteRequest): bool
    {
        return false;
    }

    public function createOffer(User $user, QuoteRequest $quoteRequest): bool
    {
        return false;
    }

    public function respondToOffer(User $user, QuoteRequest $quoteRequest): bool
    {
        return $quoteRequest->user_id === $user->id;
    }
}
