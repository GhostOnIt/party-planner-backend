<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Photo\PublicStorePhotoRequest;
use App\Http\Requests\Photo\StorePhotoRequest;
use App\Models\Event;
use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        $this->authorize('viewAny', [Photo::class, $event]);

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
        $this->authorize('viewAny', [Photo::class, $event]);

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
        $this->authorize('view', $photo);

        $photo->load('uploadedBy');

        return response()->json($photo);
    }

    /**
     * Upload photos to the gallery.
     */
    public function store(StorePhotoRequest $request, Event $event): JsonResponse
    {
        $this->authorize('upload', [Photo::class, $event]);

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
        $this->authorize('update', $photo);

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
        $this->authorize('delete', $photo);

        $this->photoService->delete($photo);

        return response()->json(null, 204);
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('setFeatured', $photo);

        $photo = $this->photoService->toggleFeatured($photo);

        return response()->json($photo);
    }

    /**
     * Set as the only featured photo.
     */
    public function setFeatured(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('setFeatured', $photo);

        $photo = $this->photoService->setAsFeatured($photo);

        return response()->json($photo);
    }

    /**
     * Bulk delete photos.
     */
    public function bulkDelete(Request $request, Event $event): JsonResponse
    {
        // For bulk operations, check if user can delete any photos for this event
        $this->authorize('viewAny', [Photo::class, $event]); // Basic access check
        // Note: Individual photo permissions are checked in the service

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
        $this->authorize('viewAny', [Photo::class, $event]); // Basic access check

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

    /**
     * Download multiple photos as ZIP.
     */
    public function bulkDownload(Request $request, Event $event)
    {
        $this->authorize('download', [Photo::class, $event]);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'integer'],
        ]);

        // Convert all photo IDs to integers
        $photoIds = array_map('intval', $validated['photos']);

        // Validate that all photos exist and belong to this event
        $existingPhotos = Photo::whereIn('id', $photoIds)
            ->where('event_id', $event->id)
            ->pluck('id')
            ->toArray();

        if (count($existingPhotos) !== count($photoIds)) {
            return response()->json([
                'message' => 'Certaines photos n\'existent pas ou n\'appartiennent pas à cet événement.',
            ], 422);
        }

        try {
            $zipPath = $this->photoService->downloadMultiple($event, $photoIds);
            $filename = Str::slug($event->title) . '-photos-' . now()->format('Y-m-d') . '.zip';

            // Return file download
            return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du fichier ZIP.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Public: Get photos for an event (no auth required, token validated).
     */
    public function publicIndex(Request $request, Event $event, string $token): JsonResponse
    {
        // Validate token
        $guest = $this->photoService->validatePhotoUploadToken($event, $token);
        if (!$guest) {
            return response()->json([
                'message' => 'Token invalide ou invité non vérifié.',
            ], 403);
        }

        $query = $event->photos();

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

        return response()->json([
            'photos' => $photos,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->date,
                'location' => $event->location,
            ],
            'guest' => [
                'name' => $guest->name,
            ],
        ]);
    }

    /**
     * Public: Upload photos (no auth required, token validated).
     */
    public function publicStore(PublicStorePhotoRequest $request, Event $event, string $token): JsonResponse
    {
        // Validate token
        $guest = $this->photoService->validatePhotoUploadToken($event, $token);
        if (!$guest) {
            return response()->json([
                'message' => 'Token invalide ou invité non vérifié.',
            ], 403);
        }

        $files = $request->file('photos');
        $count = is_array($files) ? count($files) : 1;

        // Check if event can accept more photos
        if (!$this->photoService->canAddPhotos($event, $count)) {
            $remaining = $this->photoService->getRemainingSlots($event);
            return response()->json([
                'message' => "Vous ne pouvez ajouter que {$remaining} photo(s) supplémentaire(s).",
                'remaining_slots' => $remaining,
            ], 422);
        }

        $photos = $this->photoService->uploadPublic(
            $event,
            is_array($files) ? $files : [$files],
            $token,
            $guest->name
        );

        return response()->json([
            'message' => "{$photos->count()} photo(s) uploadée(s) avec succès.",
            'photos' => $photos,
        ], 201);
    }

    /**
     * Download a single photo.
     */
    public function download(Event $event, Photo $photo)
    {
        $this->authorize('download', [Photo::class, $event]);

        // Get the file path from the photo URL
        $path = str_replace('/storage/', '', $photo->url);

        // Check if file exists
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'Photo introuvable.',
            ], 404);
        }

        // Use Laravel's download response which handles headers correctly
        return \Illuminate\Support\Facades\Storage::disk('public')->download(
            $path,
            $photo->original_name ?? 'photo.jpg'
        );
    }

    /**
     * Public: Download multiple photos as ZIP (no auth required, token validated).
     */
    public function publicDownloadMultiple(Request $request, Event $event, string $token)
    {
        // Validate token
        $guest = $this->photoService->validatePhotoUploadToken($event, $token);
        if (!$guest) {
            return response()->json([
                'message' => 'Token invalide ou invité non vérifié.',
            ], 403);
        }

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required'],
        ]);

        // Convert all photo IDs to integers (in case they come as strings from JSON)
        $photoIds = array_map(function ($id) {
            return is_numeric($id) ? (int) $id : $id;
        }, $validated['photos']);

        // Validate that all IDs are valid integers and exist
        foreach ($photoIds as $id) {
            if (!is_int($id) || $id <= 0) {
                return response()->json([
                    'message' => 'Les IDs de photos doivent être des entiers valides.',
                    'errors' => ['photos' => ['Les IDs de photos doivent être des entiers valides.']],
                ], 422);
            }
        }

        // Check if all photos exist
        $existingPhotos = \App\Models\Photo::whereIn('id', $photoIds)->pluck('id')->toArray();
        if (count($existingPhotos) !== count($photoIds)) {
            return response()->json([
                'message' => 'Certaines photos n\'existent pas.',
                'errors' => ['photos' => ['Certaines photos n\'existent pas.']],
            ], 422);
        }
        $photosCount = $event->photos()->whereIn('id', $photoIds)->count();
        if ($photosCount !== count($photoIds)) {
            return response()->json([
                'message' => 'Certaines photos n\'appartiennent pas à cet événement.',
            ], 422);
        }

        try {
            $zipPath = $this->photoService->downloadMultiple($event, $photoIds);
            $filename = Str::slug($event->title) . '-photos-' . now()->format('Y-m-d') . '.zip';

            // Return file download
            $response = response()->download($zipPath, $filename)->deleteFileAfterSend(true);

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du fichier ZIP.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
