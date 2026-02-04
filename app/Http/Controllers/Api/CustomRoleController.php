<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomRole\StoreCustomRoleRequest;
use App\Http\Requests\CustomRole\UpdateCustomRoleRequest;
use App\Models\Event;
use App\Services\CustomRoleService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomRoleController extends Controller
{
    public function __construct(
        private CustomRoleService $customRoleService,
        private PermissionService $permissionService
    ) {}

    /**
     * Get all roles for an event (system + custom).
     */
    public function index(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $roles = $this->customRoleService->getRolesForEvent($event);

        return response()->json([
            'roles' => $roles,
        ]);
    }

    /**
     * Get permissions grouped by module for role creation.
     */
    public function permissions(): JsonResponse
    {
        $permissions = $this->permissionService->getPermissionsGroupedByModule();

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create a custom role.
     */
    public function store(StoreCustomRoleRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event); // Only coordinators and owners can create roles

        $role = $this->customRoleService->createRole($event, $request->user(), $request->validated());

        return response()->json([
            'role' => $role,
            'message' => 'Rôle créé avec succès.',
        ], 201);
    }

    /**
     * Update a custom role.
     */
    public function update(UpdateCustomRoleRequest $request, Event $event, $roleId): JsonResponse
    {
        $this->authorize('update', $event);

        // For custom roles, we need to find the role by ID
        // For system roles, we don't allow updates
        $role = $event->customRoles()->findOrFail($roleId);

        $updatedRole = $this->customRoleService->updateRole($role, $request->validated());

        return response()->json([
            'role' => $updatedRole,
            'message' => 'Rôle mis à jour avec succès.',
        ]);
    }

    /**
     * Delete a custom role.
     */
    public function destroy(Event $event, $roleId): JsonResponse
    {
        $this->authorize('update', $event);

        $role = $event->customRoles()->findOrFail($roleId);

        // A role cannot be deleted if it is assigned to at least one collaborator
        // (business rule enforced here for clearer API message).
        if ($role->collaborators()->exists()) {
            return response()->json([
                'message' => 'Ce rôle est assigné à au moins un collaborateur et ne peut pas être supprimé.',
            ], 422);
        }

        $this->customRoleService->deleteRole($role);

        return response()->json([
            'message' => 'Rôle supprimé avec succès.',
        ]);
    }

    /**
     * Get available system roles for assignment (no custom roles here; event custom roles are loaded per event).
     */
    public function availableRoles(Request $request): JsonResponse
    {
        $systemRoles = [];

        foreach (\App\Enums\CollaboratorRole::assignableRoles() as $roleEnum) {
            $systemRoles[] = [
                'value' => $roleEnum->value,
                'label' => $roleEnum->label(),
                'description' => $roleEnum->description(),
                'color' => $roleEnum->color(),
                'icon' => $roleEnum->icon(),
                'is_system' => true,
            ];
        }

        return response()->json([
            'roles' => $systemRoles,
        ]);
    }
}
