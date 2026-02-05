<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTemplate;
use App\Services\AdminActivityService;
use App\Services\EventTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTemplateController extends Controller
{
    public function __construct(
        protected EventTemplateService $templateService,
        protected AdminActivityService $activityService
    ) {}

    /**
     * Display a listing of active templates.
     * Tous les templates actifs sont visibles par tout utilisateur authentifié (pas de filtre par propriétaire).
     */
    public function index(): JsonResponse
    {
        $templates = $this->templateService->getActiveTemplates();
        $groupedTemplates = $this->templateService->getTemplatesGroupedByType();

        return response()->json([
            'templates' => $templates,
            'grouped' => $groupedTemplates,
        ]);
    }

    /**
     * Display a specific template.
     */
    public function show(EventTemplate $template): JsonResponse
    {
        $preview = $this->templateService->previewApplication($template);

        return response()->json($preview);
    }

    /**
     * Get templates by event type.
     * Tous les templates du type sont visibles par tout utilisateur authentifié (pas de filtre par propriétaire).
     */
    public function byType(string $type): JsonResponse
    {
        $templates = $this->templateService->getTemplatesByType($type);
        $themes = $this->templateService->getSuggestedThemes($type);

        return response()->json([
            'templates' => $templates,
            'themes' => $themes,
        ]);
    }

    /**
     * Preview template application.
     */
    public function preview(EventTemplate $template): JsonResponse
    {
        return response()->json($this->templateService->previewApplication($template));
    }

    /**
     * Apply template to an event.
     */
    public function apply(Request $request, Event $event, EventTemplate $template): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'apply_tasks' => 'boolean',
            'apply_budget' => 'boolean',
            'apply_theme' => 'boolean',
            'theme' => 'nullable|string|max:255',
        ]);

        $results = $this->templateService->applyToEvent($template, $event, $validated);

        return response()->json([
            'message' => 'Template appliqué avec succès.',
            'results' => $results,
        ]);
    }

    /**
     * Get suggested themes for an event type.
     */
    public function themes(string $type): JsonResponse
    {
        $themes = $this->templateService->getSuggestedThemes($type);

        return response()->json(['themes' => $themes]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Methods (require admin middleware)
    |--------------------------------------------------------------------------
    */

    /**
     * List all templates for admin (including inactive).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = EventTemplate::query();

        // Filter by event type
        if ($type = $request->input('type')) {
            $query->where('event_type', $type);
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $templates = $query->paginate($request->input('per_page', 15));

        return response()->json($templates);
    }

    /**
     * Store a new template (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        // Decode JSON strings from FormData if present
        $data = $request->all();
        if ($request->has('default_tasks') && is_string($request->input('default_tasks'))) {
            $data['default_tasks'] = json_decode($request->input('default_tasks'), true);
        }
        if ($request->has('default_budget_categories') && is_string($request->input('default_budget_categories'))) {
            $data['default_budget_categories'] = json_decode($request->input('default_budget_categories'), true);
        }
        if ($request->has('suggested_themes') && is_string($request->input('suggested_themes'))) {
            $data['suggested_themes'] = json_decode($request->input('suggested_themes'), true);
        }
        if ($request->has('is_active') && is_string($request->input('is_active'))) {
            $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        // Merge decoded data back into request
        $request->merge($data);

        $validated = $request->validate([
            'event_type' => 'required|in:mariage,anniversaire,baby_shower,soiree,brunch,autre',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_tasks' => 'nullable|array',
            'default_tasks.*.title' => 'required|string|max:255',
            'default_tasks.*.description' => 'nullable|string',
            'default_tasks.*.priority' => 'nullable|in:low,medium,high',
            'default_budget_categories' => 'nullable|array',
            'default_budget_categories.*.name' => 'required|string|max:255',
            'default_budget_categories.*.category' => 'required|string',
            'default_budget_categories.*.estimated_cost' => 'nullable|numeric|min:0',
            'suggested_themes' => 'nullable|array',
            'suggested_themes.*' => 'string|max:255',
            'cover_photo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'is_active' => 'boolean',
        ]);

        // Handle cover photo upload
        if ($request->hasFile('cover_photo')) {
            $file = $request->file('cover_photo');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('templates/cover_photos', $filename, 'public');
            $validated['cover_photo_url'] = '/storage/' . $path;
        }

        // Remove cover_photo from validated data as it's not a database field
        unset($validated['cover_photo']);

        $template = EventTemplate::create($validated);

        // Log template creation (only if admin is authenticated)
        if (auth()->user()?->isAdmin()) {
            $this->activityService->logTemplateAction('create', $template, [
                'old' => null,
                'new' => $template->toArray(),
            ]);
        }

        return response()->json([
            'message' => 'Template créé avec succès.',
            'template' => $template,
        ], 201);
    }

    /**
     * Update a template (Admin).
     */
    public function update(Request $request, EventTemplate $template): JsonResponse
    {
        // Decode JSON strings from FormData if present
        $data = $request->all();
        if ($request->has('default_tasks') && is_string($request->input('default_tasks'))) {
            $data['default_tasks'] = json_decode($request->input('default_tasks'), true);
        }
        if ($request->has('default_budget_categories') && is_string($request->input('default_budget_categories'))) {
            $data['default_budget_categories'] = json_decode($request->input('default_budget_categories'), true);
        }
        if ($request->has('suggested_themes') && is_string($request->input('suggested_themes'))) {
            $data['suggested_themes'] = json_decode($request->input('suggested_themes'), true);
        }
        if ($request->has('is_active') && is_string($request->input('is_active'))) {
            $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        // Merge decoded data back into request
        $request->merge($data);

        $validated = $request->validate([
            'event_type' => 'required|in:mariage,anniversaire,baby_shower,soiree,brunch,autre',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_tasks' => 'nullable|array',
            'default_tasks.*.title' => 'string|max:255',
            'default_tasks.*.description' => 'nullable|string',
            'default_tasks.*.priority' => 'nullable|in:low,medium,high',
            'default_budget_categories' => 'nullable|array',
            'default_budget_categories.*.name' => 'string|max:255',
            'default_budget_categories.*.category' => 'string',
            'default_budget_categories.*.estimated_cost' => 'nullable|numeric|min:0',
            'suggested_themes' => 'nullable|array',
            'suggested_themes.*' => 'string|max:255',
            'cover_photo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'is_active' => 'boolean',
        ]);

        // Handle cover photo upload
        if ($request->hasFile('cover_photo')) {
            // Delete old cover photo if exists
            if ($template->cover_photo_url) {
                $oldPath = str_replace('/storage/', '', $template->cover_photo_url);
                \Storage::disk('public')->delete($oldPath);
            }
            
            $file = $request->file('cover_photo');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('templates/cover_photos', $filename, 'public');
            $validated['cover_photo_url'] = '/storage/' . $path;
        }

        // Remove cover_photo from validated data as it's not a database field
        unset($validated['cover_photo']);

        $oldValues = $template->toArray();
        $template->update($validated);

        // Log template update (only if admin is authenticated)
        if (auth()->user()?->isAdmin()) {
            $this->activityService->logTemplateAction('update', $template, [
                'old' => $oldValues,
                'new' => $template->fresh()->toArray(),
            ]);
        }

        return response()->json([
            'message' => 'Template mis à jour.',
            'template' => $template,
        ]);
    }

    /**
     * Delete a template (Admin).
     */
    public function destroy(EventTemplate $template): JsonResponse
    {
        // Log before deletion (only if admin is authenticated)
        if (auth()->user()?->isAdmin()) {
            $this->activityService->logTemplateAction('delete', $template, [
                'old' => $template->toArray(),
                'new' => null,
            ]);
        }

        $template->delete();

        return response()->json([
            'message' => 'Template supprimé.',
        ]);
    }

    /**
     * Toggle template active status (Admin).
     */
    public function toggleActive(EventTemplate $template): JsonResponse
    {
        $oldStatus = $template->is_active;
        $template->update(['is_active' => !$template->is_active]);

        // Log toggle action (only if admin is authenticated)
        if (auth()->user()?->isAdmin()) {
            $this->activityService->logTemplateAction('toggle_active', $template, [
                'old' => ['is_active' => $oldStatus],
                'new' => ['is_active' => $template->is_active],
            ]);
        }

        return response()->json([
            'message' => 'Statut mis à jour.',
            'is_active' => $template->is_active,
        ]);
    }
}
