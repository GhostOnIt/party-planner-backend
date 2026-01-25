<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLegalPageController extends Controller
{
    /**
     * Get all legal pages (admin).
     */
    public function index(): JsonResponse
    {
        $pages = LegalPage::with('updatedBy:id,name')
            ->orderBy('slug')
            ->get();

        return response()->json($pages);
    }

    /**
     * Get a specific legal page by ID (admin).
     */
    public function show(int $id): JsonResponse
    {
        $page = LegalPage::with('updatedBy:id,name')->findOrFail($id);

        return response()->json($page);
    }

    /**
     * Update a legal page (admin).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $page = LegalPage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'is_published' => 'sometimes|boolean',
        ]);

        $validated['updated_by_user_id'] = $request->user()->id;

        $page->update($validated);

        return response()->json($page->fresh()->load('updatedBy:id,name'));
    }

    /**
     * Create a new legal page (admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100|unique:legal_pages,slug',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_published' => 'sometimes|boolean',
        ]);

        $validated['updated_by_user_id'] = $request->user()->id;
        $validated['is_published'] = $validated['is_published'] ?? true;

        $page = LegalPage::create($validated);

        return response()->json($page->load('updatedBy:id,name'), 201);
    }

    /**
     * Delete a legal page (admin).
     */
    public function destroy(int $id): JsonResponse
    {
        $page = LegalPage::findOrFail($id);
        $page->delete();

        return response()->json(['message' => 'Page supprimée avec succès']);
    }
}
