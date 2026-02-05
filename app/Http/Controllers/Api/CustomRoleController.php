<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Get all roles for an event (system + event owner's custom roles). Read-only; custom roles are managed in settings.
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
     * Get permissions grouped by module for role creation (used in settings).
     */
    public function permissions(): JsonResponse
    {
        $permissions = $this->permissionService->getPermissionsGroupedByModule();

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    /**
     * Get available system roles for assignment (global).
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
