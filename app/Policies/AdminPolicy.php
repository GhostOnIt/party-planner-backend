<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    /**
     * Determine whether the user can access admin features.
     */
    public function access(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the admin dashboard.
     */
    public function viewDashboard(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage users.
     */
    public function manageUsers(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view a specific user.
     */
    public function viewUser(User $user, User $targetUser): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update a user's role.
     */
    public function updateUserRole(User $user, User $targetUser): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        // Cannot change own role
        if ($user->id === $targetUser->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete a user.
     */
    public function deleteUser(User $user, User $targetUser): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        // Cannot delete self
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Cannot delete other admins
        if ($targetUser->isAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view all events.
     */
    public function viewAllEvents(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view all payments.
     */
    public function viewAllPayments(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view all subscriptions.
     */
    public function viewAllSubscriptions(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view activity logs.
     */
    public function viewActivityLogs(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage templates.
     */
    public function manageTemplates(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create templates.
     */
    public function createTemplate(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update templates.
     */
    public function updateTemplate(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete templates.
     */
    public function deleteTemplate(User $user): bool
    {
        return $user->isAdmin();
    }
}
