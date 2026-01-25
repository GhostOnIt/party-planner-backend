<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\JsonResponse;

class LegalPageController extends Controller
{
    /**
     * Get a published legal page by slug (public).
     */
    public function show(string $slug): JsonResponse
    {
        $page = LegalPage::published()
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return response()->json(['message' => 'Page non trouvée'], 404);
        }

        return response()->json([
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'updated_at' => $page->updated_at,
        ]);
    }
}
