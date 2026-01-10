<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collaborator extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'custom_role_id',
        'invited_at',
        'accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the event that the collaborator belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user that is the collaborator.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the custom role for this collaborator.
     */
    public function customRole(): BelongsTo
    {
        return $this->belongsTo(CustomRole::class);
    }

    /**
     * Check if the collaborator is the owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if the collaborator can edit.
     */
    public function canEdit(): bool
    {
        return in_array($this->role, ['owner', 'editor', 'coordinator']);
    }

    /**
     * Check if the collaborator has a custom role.
     */
    public function hasCustomRole(): bool
    {
        return $this->custom_role_id !== null;
    }

    /**
     * Get the effective role name (custom role name or system role).
     */
    public function getEffectiveRoleName(): string
    {
        if ($this->hasCustomRole() && $this->customRole) {
            return $this->customRole->name;
        }

        return $this->getSystemRoleDisplayName();
    }

    /**
     * Get the display name for system roles.
     */
    public function getSystemRoleDisplayName(): string
    {
        return match($this->role) {
            'owner' => 'Propriétaire',
            'coordinator' => 'Coordinateur',
            'guest_manager' => 'Gestionnaire d\'Invités',
            'planner' => 'Planificateur',
            'accountant' => 'Comptable',
            'photographer' => 'Photographe',
            'supervisor' => 'Superviseur',
            'reporter' => 'Rapporteur',
            'editor' => 'Éditeur', // Legacy
            'viewer' => 'Lecteur', // Legacy
            default => ucfirst($this->role),
        };
    }

    /**
     * Get the role color for UI.
     */
    public function getRoleColor(): string
    {
        if ($this->hasCustomRole() && $this->customRole) {
            return $this->customRole->getColorClass();
        }

        return $this->getSystemRoleColor();
    }

    /**
     * Get the color for system roles.
     */
    public function getSystemRoleColor(): string
    {
        return match($this->role) {
            'owner' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'coordinator' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
            'guest_manager' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            'planner' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'accountant' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
            'photographer' => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
            'supervisor' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
            'reporter' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
            'editor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300', // Legacy
            'viewer' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300', // Legacy
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        };
    }

    /**
     * Check if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
