<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetItem;
use App\Models\Event;
use App\Models\UserBudgetCategory;
use App\Models\UserCollaboratorRole;
use App\Models\UserEventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get user's event types.
     */
    public function getEventTypes(Request $request): JsonResponse
    {
        $user = $request->user();
        $eventTypes = $user->eventTypes()->ordered()->get();

        return response()->json([
            'data' => $eventTypes,
        ]);
    }

    /**
     * Create a new event type.
     */
    public function createEventType(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('user_event_types')->where('user_id', $user->id),
            ],
            'color' => 'nullable|string|max:50',
        ]);

        // Generate slug from name if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Get max order
        $maxOrder = $user->eventTypes()->max('order') ?? 0;

        $eventType = UserEventType::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'color' => $validated['color'] ?? 'gray',
            'is_default' => false,
            'order' => $maxOrder + 1,
        ]);

        return response()->json([
            'message' => 'Type d\'événement créé avec succès.',
            'data' => $eventType,
        ], 201);
    }

    /**
     * Update an event type.
     */
    public function updateEventType(Request $request, UserEventType $eventType): JsonResponse
    {
        $user = $request->user();

        // Ensure the event type belongs to the user
        if ($eventType->user_id !== $user->id) {
            return response()->json([
                'message' => 'Type d\'événement non trouvé.',
            ], 404);
        }

        // Prevent editing default types (optional - you can remove this if you want to allow editing)
        if ($eventType->is_default) {
            return response()->json([
                'message' => 'Les types par défaut ne peuvent pas être modifiés.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('user_event_types')->where('user_id', $user->id)->ignore($eventType->id),
            ],
            'color' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0',
        ]);

        $eventType->update($validated);

        return response()->json([
            'message' => 'Type d\'événement modifié avec succès.',
            'data' => $eventType->fresh(),
        ]);
    }

    /**
     * Delete an event type.
     */
    public function deleteEventType(Request $request, UserEventType $eventType): JsonResponse
    {
        $user = $request->user();

        // Ensure the event type belongs to the user
        if ($eventType->user_id !== $user->id) {
            return response()->json([
                'message' => 'Type d\'événement non trouvé.',
            ], 404);
        }

        // Prevent deleting default types
        if ($eventType->is_default) {
            return response()->json([
                'message' => 'Les types par défaut ne peuvent pas être supprimés.',
            ], 403);
        }

        // Check if event type is used in any events
        $eventsCount = Event::where('user_id', $user->id)
            ->where('type', $eventType->slug)
            ->count();

        if ($eventsCount > 0) {
            return response()->json([
                'message' => 'Ce type d\'événement est utilisé dans ' . $eventsCount . ' événement(s). Veuillez modifier ou supprimer ces événements avant de supprimer le type.',
            ], 422);
        }

        $eventType->delete();

        return response()->json([
            'message' => 'Type d\'événement supprimé avec succès.',
        ]);
    }

    /**
     * Reorder event types.
     */
    public function reorderEventTypes(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:user_event_types,id',
            'order.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['order'] as $item) {
            $eventType = UserEventType::find($item['id']);
            if ($eventType && $eventType->user_id === $user->id) {
                $eventType->update(['order' => $item['order']]);
            }
        }

        return response()->json([
            'message' => 'Ordre des types d\'événement mis à jour.',
        ]);
    }

    /**
     * Get user's collaborator roles.
     */
    public function getCollaboratorRoles(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->collaboratorRoles()->ordered()->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Create a new collaborator role.
     */
    public function createCollaboratorRole(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('user_collaborator_roles')->where('user_id', $user->id),
            ],
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        // Generate slug from name if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Get max order
        $maxOrder = $user->collaboratorRoles()->max('order') ?? 0;

        $role = UserCollaboratorRole::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? [],
            'is_default' => false,
            'order' => $maxOrder + 1,
        ]);

        return response()->json([
            'message' => 'Rôle créé avec succès.',
            'data' => $role,
        ], 201);
    }

    /**
     * Update a collaborator role.
     */
    public function updateCollaboratorRole(Request $request, UserCollaboratorRole $role): JsonResponse
    {
        $user = $request->user();

        // Ensure the role belongs to the user
        if ($role->user_id !== $user->id) {
            return response()->json([
                'message' => 'Rôle non trouvé.',
            ], 404);
        }

        // Prevent editing default roles
        if ($role->is_default) {
            return response()->json([
                'message' => 'Les rôles par défaut ne peuvent pas être modifiés.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('user_collaborator_roles')->where('user_id', $user->id)->ignore($role->id),
            ],
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'order' => 'nullable|integer|min:0',
        ]);

        $role->update($validated);

        return response()->json([
            'message' => 'Rôle modifié avec succès.',
            'data' => $role->fresh(),
        ]);
    }

    /**
     * Delete a collaborator role.
     */
    public function deleteCollaboratorRole(Request $request, UserCollaboratorRole $role): JsonResponse
    {
        $user = $request->user();

        // Ensure the role belongs to the user
        if ($role->user_id !== $user->id) {
            return response()->json([
                'message' => 'Rôle non trouvé.',
            ], 404);
        }

        // Prevent deleting default roles
        if ($role->is_default) {
            return response()->json([
                'message' => 'Les rôles par défaut ne peuvent pas être supprimés.',
            ], 403);
        }

        // Check if role is used in any collaborators
        // Note: This would require checking the collaborators table
        // For now, we'll allow deletion but you might want to add this check

        $role->delete();

        return response()->json([
            'message' => 'Rôle supprimé avec succès.',
        ]);
    }

    /**
     * Reorder collaborator roles.
     */
    public function reorderCollaboratorRoles(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:user_collaborator_roles,id',
            'order.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['order'] as $item) {
            $role = UserCollaboratorRole::find($item['id']);
            if ($role && $role->user_id === $user->id) {
                $role->update(['order' => $item['order']]);
            }
        }

        return response()->json([
            'message' =>             'Ordre des rôles mis à jour.',
        ]);
    }

    /**
     * Get user's budget categories.
     */
    public function getBudgetCategories(Request $request): JsonResponse
    {
        $user = $request->user();
        $categories = $user->budgetCategories()->ordered()->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Create a new budget category.
     */
    public function createBudgetCategory(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('user_budget_categories')->where('user_id', $user->id),
            ],
            'color' => 'nullable|string|max:50',
        ]);

        // Generate slug from name if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Get max order
        $maxOrder = $user->budgetCategories()->max('order') ?? 0;

        $category = UserBudgetCategory::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'color' => $validated['color'] ?? 'gray',
            'is_default' => false,
            'order' => $maxOrder + 1,
        ]);

        return response()->json([
            'message' => 'Catégorie de budget créée avec succès.',
            'data' => $category,
        ], 201);
    }

    /**
     * Update a budget category.
     */
    public function updateBudgetCategory(Request $request, UserBudgetCategory $category): JsonResponse
    {
        $user = $request->user();

        // Ensure the category belongs to the user
        if ($category->user_id !== $user->id) {
            return response()->json([
                'message' => 'Catégorie de budget non trouvée.',
            ], 404);
        }

        // Prevent editing default categories
        if ($category->is_default) {
            return response()->json([
                'message' => 'Les catégories par défaut ne peuvent pas être modifiées.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('user_budget_categories')->where('user_id', $user->id)->ignore($category->id),
            ],
            'color' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Catégorie de budget modifiée avec succès.',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Delete a budget category.
     */
    public function deleteBudgetCategory(Request $request, UserBudgetCategory $category): JsonResponse
    {
        $user = $request->user();

        // Ensure the category belongs to the user
        if ($category->user_id !== $user->id) {
            return response()->json([
                'message' => 'Catégorie de budget non trouvée.',
            ], 404);
        }

        // Prevent deleting default categories
        if ($category->is_default) {
            return response()->json([
                'message' => 'Les catégories par défaut ne peuvent pas être supprimées.',
            ], 403);
        }

        // Check if category is used in any budget items
        $budgetItemsCount = BudgetItem::whereHas('event', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('category', $category->slug)
            ->count();

        if ($budgetItemsCount > 0) {
            return response()->json([
                'message' => 'Cette catégorie est utilisée dans ' . $budgetItemsCount . ' élément(s) de budget. Veuillez modifier ou supprimer ces éléments avant de supprimer la catégorie.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Catégorie de budget supprimée avec succès.',
        ]);
    }

    /**
     * Reorder budget categories.
     */
    public function reorderBudgetCategories(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:user_budget_categories,id',
            'order.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['order'] as $item) {
            $category = UserBudgetCategory::find($item['id']);
            if ($category && $category->user_id === $user->id) {
                $category->update(['order' => $item['order']]);
            }
        }

        return response()->json([
            'message' => 'Ordre des catégories de budget mis à jour.',
        ]);
    }
}
