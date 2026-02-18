<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomRole extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'color',
        'is_system',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this role (creator/owner; custom roles are unique per user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created this role.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the permissions for this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'custom_role_permissions');
    }

    /**
     * Get the collaborators with this role.
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(Collaborator::class, 'collaborator_custom_roles')
            ->withTimestamps();
    }

    /**
     * Check if this is a system role.
     */
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * Check if this role has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Check if this role has any permission in a module.
     */
    public function hasAnyPermissionInModule(string $module): bool
    {
        return $this->permissions()->where('module', $module)->exists();
    }

    /**
     * Get permission names for this role.
     */
    public function getPermissionNames(): array
    {
        return $this->permissions()->pluck('name')->toArray();
    }

    /**
     * Assign permissions to this role.
     */
    public function assignPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Get the color class for UI.
     */
    public function getColorClass(): string
    {
        return match($this->color) {
            'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'red' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        };
    }

    /**
     * Get the icon for this role type.
     */
    public function getIcon(): string
    {
        if ($this->isSystem()) {
            return match($this->name) {
                'Propriétaire' => 'crown',
                'Coordinateur' => 'target',
                'Gestionnaire d\'Invités' => 'users',
                'Planificateur' => 'checklist',
                'Comptable' => 'money',
                'Photographe' => 'camera',
                'Superviseur' => 'eye',
                'Rapporteur' => 'file-text',
                default => 'user',
            };
        }

        return 'user';
    }

    /**
     * Scope to get only system roles.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to get only custom roles.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope to filter by owner user (custom roles are unique per user).
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
