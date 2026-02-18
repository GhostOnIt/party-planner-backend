<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CollaborationInvitation extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'email',
        'roles',
        'custom_role_ids',
        'token',
        'invited_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'custom_role_ids' => 'array',
            'invited_at' => 'datetime',
        ];
    }

    /**
     * Get the event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get role names for display (e.g. in email).
     */
    public function getRoleNames(): array
    {
        $names = [];
        $roles = $this->roles ?? [];
        foreach ($roles as $role) {
            $names[] = $this->getSystemRoleLabel($role);
        }
        $customIds = $this->custom_role_ids ?? [];
        if (!empty($customIds)) {
            $customRoles = CustomRole::whereIn('id', $customIds)->get();
            foreach ($customRoles as $cr) {
                $names[] = $cr->name;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Get a single label for all roles (for email).
     */
    public function getRoleLabel(): string
    {
        $names = $this->getRoleNames();
        if (count($names) > 1) {
            $last = array_pop($names);
            return implode(', ', $names) . ' et ' . $last;
        }
        return $names[0] ?? 'Collaborateur';
    }

    protected function getSystemRoleLabel(string $role): string
    {
        return match ($role) {
            'owner' => 'Propriétaire',
            'coordinator' => 'Coordinateur',
            'guest_manager' => 'Gestionnaire d\'Invités',
            'planner' => 'Planificateur',
            'accountant' => 'Comptable',
            'supervisor' => 'Superviseur',
            'reporter' => 'Rapporteur',
            'editor' => 'Éditeur',
            'viewer' => 'Lecteur',
            default => ucfirst($role),
        };
    }

    /**
     * Generate a secure token for the invitation link (optional).
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }
}
