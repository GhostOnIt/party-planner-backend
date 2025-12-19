<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Photo\StorePhotoRequest;
use App\Models\Event;
use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct(
        protected PhotoService $photoService
    ) {}

    /**
     * Display the photos for an event.
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $query = $event->photos()->with('uploadedBy');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by featured
        if ($request->filled('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        $photos = $query
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        $stats = $this->photoService->getStatistics($event);
        $canAddPhotos = $this->photoService->canAddPhotos($event);
        $remainingSlots = $this->photoService->getRemainingSlots($event);

        return response()->json([
            'photos' => $photos,
            'stats' => $stats,
            'can_add_photos' => $canAddPhotos,
            'remaining_slots' => $remainingSlots,
        ]);
    }

    /**
     * Get gallery statistics.
     */
    public function statistics(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $stats = $this->photoService->getStatistics($event);
        $canAddPhotos = $this->photoService->canAddPhotos($event);
        $remainingSlots = $this->photoService->getRemainingSlots($event);

        return response()->json([
            'stats' => $stats,
            'can_add_photos' => $canAddPhotos,
            'remaining_slots' => $remainingSlots,
        ]);
    }

    /**
     * Show a single photo.
     */
    public function show(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('view', $event);

        $photo->load('uploadedBy');

        return response()->json($photo);
    }

    /**
     * Upload photos to the gallery.
     */
    public function store(StorePhotoRequest $request, Event $event): JsonResponse
    {
        $files = $request->file('photos');
        $count = is_array($files) ? count($files) : 1;

        if (!$this->photoService->canAddPhotos($event, $count)) {
            $remaining = $this->photoService->getRemainingSlots($event);
            return response()->json([
                'message' => "Vous ne pouvez ajouter que {$remaining} photo(s) supplémentaire(s).",
                'remaining_slots' => $remaining,
            ], 422);
        }

        $photos = $this->photoService->uploadMultiple(
            $event,
            is_array($files) ? $files : [$files],
            $request->user(),
            $request->validated('type'),
            $request->validated('description')
        );

        return response()->json([
            'message' => "{$photos->count()} photo(s) uploadée(s) avec succès.",
            'photos' => $photos,
        ], 201);
    }

    /**
     * Update photo details.
     */
    public function update(Request $request, Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('managePhotos', $event);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'in:moodboard,event_photo'],
        ]);

        $photo->update($validated);

        return response()->json($photo->fresh());
    }

    /**
     * Remove the specified photo.
     */
    public function destroy(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('managePhotos', $event);

        $this->photoService->delete($photo);

        return response()->json(null, 204);
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('managePhotos', $event);

        $photo = $this->photoService->toggleFeatured($photo);

        return response()->json($photo);
    }

    /**
     * Set as the only featured photo.
     */
    public function setFeatured(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('managePhotos', $event);

        $photo = $this->photoService->setAsFeatured($photo);

        return response()->json($photo);
    }

    /**
     * Bulk delete photos.
     */
    public function bulkDelete(Request $request, Event $event): JsonResponse
    {
        $this->authorize('managePhotos', $event);

        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['exists:photos,id'],
        ]);

        $deleted = $this->photoService->bulkDelete($event, $request->photos);

        return response()->json([
            'message' => "{$deleted} photo(s) supprimée(s).",
            'count' => $deleted,
        ]);
    }

    /**
     * Bulk update photo type.
     */
    public function bulkUpdateType(Request $request, Event $event): JsonResponse
    {
        $this->authorize('managePhotos', $event);

        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['exists:photos,id'],
            'type' => ['required', 'in:moodboard,event_photo'],
        ]);

        $updated = $this->photoService->bulkUpdateType($event, $request->photos, $request->type);

        return response()->json([
            'message' => "{$updated} photo(s) mise(s) à jour.",
            'count' => $updated,
        ]);
    }
}
