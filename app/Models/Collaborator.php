<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
     * Get the roles for this collaborator.
     */
    public function collaboratorRoles()
    {
        return $this->hasMany(\App\Models\CollaboratorRole::class);
    }

    /**
     * Get the role values as array.
     */
    public function getRoleValues(): array
    {
        return $this->collaboratorRoles->pluck('role')->toArray();
    }

    /**
     * Check if the collaborator is the owner.
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Check if the collaborator can edit.
     */
    public function canEdit(): bool
    {
        return $this->hasAnyRole(['owner', 'editor', 'coordinator']);
    }

    /**
     * Check if collaborator has a specific role.
     */
    public function hasRole(string $role): bool
    {
        // Support both old single role system and new multiple roles system
        if ($this->relationLoaded('collaboratorRoles')) {
            return $this->collaboratorRoles->contains('role', $role);
        }

        return $this->role === $role;
    }

    /**
     * Check if collaborator has any of the specified roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        if ($this->relationLoaded('collaboratorRoles')) {
            return $this->collaboratorRoles->whereIn('role', $roles)->isNotEmpty();
        }

        return in_array($this->role, $roles);
    }

    /**
     * Check if the collaborator has a custom role.
     */
    public function hasCustomRole(): bool
    {
        return $this->custom_role_id !== null;
    }

    /**
     * Get the effective role names (custom role name or system roles).
     */
    public function getEffectiveRoleNames(): array
    {
        if ($this->hasCustomRole() && $this->customRole) {
            return [$this->customRole->name];
        }

        if ($this->relationLoaded('collaboratorRoles') && $this->collaboratorRoles->isNotEmpty()) {
            return $this->collaboratorRoles->map(function($collaboratorRole) {
                return $this->getSystemRoleDisplayName($collaboratorRole->role);
            })->toArray();
        }

        return [$this->getSystemRoleDisplayName()];
    }

    /**
     * Get the effective role name (primary role for backward compatibility).
     */
    public function getEffectiveRoleName(): string
    {
        return $this->getEffectiveRoleNames()[0] ?? 'Aucun';
    }

    /**
     * Get the display name for system roles.
     */
    public function getSystemRoleDisplayName(?string $role = null): string
    {
        $roleToCheck = $role ?? $this->role;

        return match($roleToCheck) {
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
            default => ucfirst($roleToCheck ?? ''),
        };
    }

    /**
     * Get the role colors for UI.
     */
    public function getRoleColors(): array
    {
        if ($this->hasCustomRole() && $this->customRole) {
            return [$this->customRole->getColorClass()];
        }

        if ($this->relationLoaded('collaboratorRoles') && $this->collaboratorRoles->isNotEmpty()) {
            return $this->collaboratorRoles->map(function($collaboratorRole) {
                return $this->getSystemRoleColor($collaboratorRole->role);
            })->toArray();
        }

        return [$this->getSystemRoleColor()];
    }

    /**
     * Get the role color for UI (primary color for backward compatibility).
     */
    public function getRoleColor(): string
    {
        return $this->getRoleColors()[0] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
    }

    /**
     * Get the color for system roles.
     */
    public function getSystemRoleColor(?string $role = null): string
    {
        $roleToCheck = $role ?? $this->role;

        return match($roleToCheck) {
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

    /**
     * Convert model to array with roles.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Add roles array for API responses
        $array['roles'] = $this->getRoleValues();

        return $array;
    }

    /**
     * Always load roles relationship.
     */
    protected static function booted()
    {
        static::addGlobalScope('withRoles', function ($builder) {
            $builder->with('collaboratorRoles');
        });
    }
}
