<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequestStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQuoteStageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => QuoteRequestStage::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:quote_request_stages,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $stage = QuoteRequestStage::create([
            ...$validated,
            'is_system' => false,
        ]);

        return response()->json([
            'message' => 'Colonne créée.',
            'data' => $stage,
        ], 201);
    }

    public function update(Request $request, QuoteRequestStage $stage): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:quote_request_stages,slug,' . $stage->id],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $stage->update($validated);

        return response()->json([
            'message' => 'Colonne mise à jour.',
            'data' => $stage->fresh(),
        ]);
    }

    public function destroy(QuoteRequestStage $stage): JsonResponse
    {
        if ($stage->is_system) {
            return response()->json(['message' => 'Impossible de supprimer une colonne système.'], 422);
        }

        if ($stage->quoteRequests()->exists()) {
            return response()->json(['message' => 'Impossible: des demandes sont encore dans cette colonne.'], 422);
        }

        $stage->delete();

        return response()->json(['message' => 'Colonne supprimée.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.id' => ['required', 'exists:quote_request_stages,id'],
            'stages.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['stages'] as $stagePayload) {
            QuoteRequestStage::whereKey($stagePayload['id'])->update([
                'sort_order' => $stagePayload['sort_order'],
            ]);
        }

        return response()->json([
            'message' => 'Ordre des colonnes mis à jour.',
            'data' => QuoteRequestStage::query()->orderBy('sort_order')->get(),
        ]);
    }
}
