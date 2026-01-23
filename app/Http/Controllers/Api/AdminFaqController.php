<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminFaqController extends Controller
{
    /**
     * Display a listing of all FAQs (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $faqs = Faq::orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($faqs);
    }

    /**
     * Store a newly created FAQ.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|min:1',
            'answer' => 'required|string|min:1',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        // If order is not provided, set it to the max order + 1
        if (!isset($validated['order'])) {
            $maxOrder = Faq::max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        $faq = Faq::create($validated);

        return response()->json($faq, 201);
    }

    /**
     * Update the specified FAQ.
     */
    public function update(Request $request, Faq $faq): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'sometimes|required|string|min:1',
            'answer' => 'sometimes|required|string|min:1',
            'order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $faq->update($validated);

        return response()->json($faq);
    }

    /**
     * Remove the specified FAQ.
     */
    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json([
            'message' => 'FAQ supprimée avec succès.',
        ]);
    }
}
