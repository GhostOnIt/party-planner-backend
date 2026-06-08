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

        $query = $this->photoService
            ->applyVisibility($event->photos()->with(['uploadedBy', 'moderatedBy']), $request->user(), $event);

        // Search by uploader name or email
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('uploadedBy', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by featured
        if ($request->filled('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        if ($request->filled('moderation_status') && $request->user()->can('moderate', [Photo::class, $event])) {
            $query->where('moderation_status', $request->input('moderation_status'));
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

        $photo->load(['uploadedBy', 'moderatedBy']);

        return response()->json($photo);
    }

    /**
     * Upload photos to the gallery.
     */
    public function store(StorePhotoRequest $request, Event $event): JsonResponse
    {
        set_time_limit(120);
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

        try {
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
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
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

        if (!$photo->isApproved()) {
            return response()->json([
                'message' => 'Cette photo doit etre validee avant de pouvoir etre mise en avant.',
            ], 422);
        }

        $photo = $this->photoService->toggleFeatured($photo);

        return response()->json($photo);
    }

    /**
     * Set as the only featured photo.
     */
    public function setFeatured(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('setFeatured', $photo);

        if (!$photo->isApproved()) {
            return response()->json([
                'message' => 'Cette photo doit etre validee avant de pouvoir etre mise en avant.',
            ], 422);
        }

        $photo = $this->photoService->setAsFeatured($photo);

        return response()->json($photo);
    }

    /**
     * Bulk delete photos.
     */
    public function bulkDelete(Request $request, Event $event): JsonResponse
    {
        // For bulk operations, check if user can delete any photos for this event
        $this->authorize('delete', Photo::make(['event_id' => $event->id]));

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
        $this->authorize('setFeatured', Photo::make(['event_id' => $event->id]));

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
        $this->authorize('viewAny', [Photo::class, $event]);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'string', 'exists:photos,id'],
        ]);

        $photoIds = $validated['photos'];

        // Validate that all photos exist and belong to this event
        $existingPhotos = $this->photoService
            ->applyVisibility(Photo::whereIn('id', $photoIds), $request->user(), $event)
            ->where('event_id', $event->id)
            ->where('moderation_status', 'approved')
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
            \Illuminate\Support\Facades\Log::error('Photo ZIP download failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création du fichier ZIP. Veuillez réessayer.',
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

        $query = $event->photos()->where('moderation_status', 'approved');

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
        set_time_limit(120);
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

        try {
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
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a single photo.
     */
    public function download(Event $event, Photo $photo)
    {
        $this->authorize('download', $photo);

        $path = \App\Helpers\StorageHelper::urlToPath($photo->url);
        $disk = \App\Helpers\StorageHelper::diskForUrl($photo->url);

        if (!$path || !$disk->exists($path)) {
            return response()->json([
                'message' => 'Photo introuvable.',
            ], 404);
        }

        return $disk->download($path, $photo->original_name ?? 'photo.jpg');
    }

    public function approve(Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('moderate', [Photo::class, $event]);

        if ($photo->event_id !== $event->id) {
            return response()->json(['message' => 'Photo introuvable pour cet evenement.'], 404);
        }

        return response()->json($this->photoService->approve($photo, request()->user()));
    }

    public function reject(Request $request, Event $event, Photo $photo): JsonResponse
    {
        $this->authorize('moderate', [Photo::class, $event]);

        if ($photo->event_id !== $event->id) {
            return response()->json(['message' => 'Photo introuvable pour cet evenement.'], 404);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($this->photoService->reject($photo, $request->user(), $validated['reason'] ?? null));
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
        $existingPhotos = \App\Models\Photo::whereIn('id', $photoIds)
            ->where('moderation_status', 'approved')
            ->pluck('id')
            ->toArray();
        if (count($existingPhotos) !== count($photoIds)) {
            return response()->json([
                'message' => 'Certaines photos n\'existent pas ou ne sont pas encore validees.',
                'errors' => ['photos' => ['Certaines photos n\'existent pas ou ne sont pas encore validees.']],
            ], 422);
        }
        $photosCount = $event->photos()
            ->whereIn('id', $photoIds)
            ->where('moderation_status', 'approved')
            ->count();
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
            \Illuminate\Support\Facades\Log::error('Public photo ZIP download failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création du fichier ZIP. Veuillez réessayer.',
            ], 500);
        }
    }
}
