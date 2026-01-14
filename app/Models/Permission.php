<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'module',
        'action',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the custom roles that have this permission.
     */
    public function customRoles(): BelongsToMany
    {
        return $this->belongsToMany(CustomRole::class, 'custom_role_permissions');
    }

    /**
     * Check if this permission belongs to a specific module.
     */
    public function isInModule(string $module): bool
    {
        return $this->module === $module;
    }

    /**
     * Check if this permission allows a specific action.
     */
    public function allowsAction(string $action): bool
    {
        return $this->action === $action;
    }

    /**
     * Get permissions grouped by module.
     */
    public static function getGroupedByModule(): array
    {
        return static::orderBy('module')
            ->orderBy('action')
            ->get()
            ->groupBy('module')
            ->toArray();
    }

    /**
     * Get permissions for a specific module.
     */
    public static function getByModule(string $module): array
    {
        return static::where('module', $module)
            ->orderBy('action')
            ->get()
            ->toArray();
    }

    /**
     * Scope to filter permissions by module.
     */
    public function scopeInModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope to filter permissions by action.
     */
    public function scopeWithAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
