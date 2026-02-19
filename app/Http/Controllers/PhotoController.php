<?php

namespace App\Http\Controllers;

use App\Http\Requests\Photo\StorePhotoRequest;
use App\Models\Event;
use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhotoController extends Controller
{
    public function __construct(
        protected PhotoService $photoService
    ) {}

    /**
     * Display the gallery for an event.
     */
    public function index(Request $request, Event $event): View
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
            ->paginate(20);

        $moodboardPhotos = $this->photoService->getMoodboard($event);
        $eventPhotos = $this->photoService->getEventPhotos($event);
        $stats = $this->photoService->getStatistics($event);
        $canAddPhotos = $this->photoService->canAddPhotos($event);
        $remainingSlots = $this->photoService->getRemainingSlots($event);

        return view('events.gallery.index', compact(
            'event',
            'photos',
            'moodboardPhotos',
            'eventPhotos',
            'stats',
            'canAddPhotos',
            'remainingSlots'
        ));
    }

    /**
     * Show upload form.
     */
    public function create(Event $event): View|RedirectResponse
    {
        $this->authorize('managePhotos', $event);

        if (!$this->photoService->canAddPhotos($event)) {
            return redirect()
                ->route('events.gallery.index', $event)
                ->with('warning', 'Vous avez atteint la limite de photos pour cet événement. Passez à un forfait supérieur pour en ajouter plus.');
        }

        $remainingSlots = $this->photoService->getRemainingSlots($event);
        $photoTypes = \App\Enums\PhotoType::options();

        return view('events.gallery.create', compact('event', 'remainingSlots', 'photoTypes'));
    }

    /**
     * Upload photos to the gallery.
     */
    public function store(StorePhotoRequest $request, Event $event): RedirectResponse
    {
        $files = $request->file('photos');
        $count = is_array($files) ? count($files) : 1;

        if (!$this->photoService->canAddPhotos($event, $count)) {
            $remaining = $this->photoService->getRemainingSlots($event);
            return redirect()
                ->back()
                ->with('error', "Vous ne pouvez ajouter que {$remaining} photo(s) supplémentaire(s).");
        }

        $photos = $this->photoService->uploadMultiple(
            $event,
            is_array($files) ? $files : [$files],
            $request->user(),
            $request->validated('type'),
            $request->validated('description')
        );

        $uploadedCount = $photos->count();

        return redirect()
            ->route('events.gallery.index', $event)
            ->with('success', "{$uploadedCount} photo(s) uploadée(s) avec succès.");
    }

    /**
     * Show a single photo.
     */
    public function show(Event $event, Photo $photo): View
    {
        $this->authorize('view', $event);

        $prevPhoto = $event->photos()
            ->where('id', '<', $photo->id)
            ->orderBy('id', 'desc')
            ->first();

        $nextPhoto = $event->photos()
            ->where('id', '>', $photo->id)
            ->orderBy('id', 'asc')
            ->first();

        return view('events.gallery.show', compact('event', 'photo', 'prevPhoto', 'nextPhoto'));
    }

    /**
     * Show edit form for a photo.
     */
    public function edit(Event $event, Photo $photo): View
    {
        $this->authorize('managePhotos', $event);

        $photoTypes = \App\Enums\PhotoType::options();

        return view('events.gallery.edit', compact('event', 'photo', 'photoTypes'));
    }

    /**
     * Update photo details.
     */
    public function update(Request $request, Event $event, Photo $photo): RedirectResponse
    {
        $this->authorize('managePhotos', $event);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:moodboard,event_photo'],
        ]);

        $photo->update($validated);

        return redirect()
            ->route('events.gallery.index', $event)
            ->with('success', 'Photo mise à jour avec succès.');
    }

    /**
     * Remove the specified photo.
     */
    public function destroy(Event $event, Photo $photo): RedirectResponse
    {
        $this->authorize('managePhotos', $event);

        $this->photoService->delete($photo);

        return redirect()
            ->route('events.gallery.index', $event)
            ->with('success', 'Photo supprimée avec succès.');
    }

    /**
     * Toggle featured status of a photo.
     */
    public function toggleFeatured(Event $event, Photo $photo): RedirectResponse
    {
        $this->authorize('managePhotos', $event);

        $this->photoService->toggleFeatured($photo);

        $message = $photo->fresh()->is_featured
            ? 'Photo mise en avant avec succès.'
            : 'Photo retirée de la mise en avant.';

        return redirect()
            ->route('events.gallery.index', $event)
            ->with('success', $message);
    }

    /**
     * Set a photo as the only featured photo.
     */
    public function setFeatured(Event $event, Photo $photo): RedirectResponse
    {
        $this->authorize('managePhotos', $event);

        $this->photoService->setAsFeatured($photo);

        return redirect()
            ->route('events.gallery.index', $event)
            ->with('success', 'Photo définie comme photo principale.');
    }

    /**
     * Bulk delete photos.
     */
    public function bulkDelete(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('managePhotos', $event);

        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['exists:photos,id'],
        ]);

        $deleted = $this->photoService->bulkDelete($event, $request->photos);

        return redirect()
            ->route('events.gallery.index', $event)
            ->with('success', "{$deleted} photo(s) supprimée(s) avec succès.");
    }

    /**
     * Bulk update photo type.
     */
    public function bulkUpdateType(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('managePhotos', $event);

        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['exists:photos,id'],
            'type' => ['required', 'in:moodboard,event_photo'],
        ]);

        $updated = $this->photoService->bulkUpdateType($event, $request->photos, $request->type);

        $typeLabel = $request->type === 'moodboard' ? 'moodboard' : 'photos de l\'événement';

        return redirect()
            ->route('events.gallery.index', $event)
            ->with('success', "{$updated} photo(s) déplacée(s) vers {$typeLabel}.");
    }

    /**
     * Get gallery statistics (JSON for AJAX).
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
     * Download a photo.
     */
    public function download(Event $event, Photo $photo)
    {
        $this->authorize('view', $event);

        $path = \App\Helpers\StorageHelper::urlToPath($photo->url);
        $disk = \App\Helpers\StorageHelper::diskForUrl($photo->url);

        if (!$path || !$disk->exists($path)) {
            return redirect()
                ->route('events.gallery.index', $event)
                ->with('error', 'Photo introuvable.');
        }

        $filename = basename($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

        return response()->streamDownload(
            function () use ($disk, $path) {
                echo $disk->get($path);
            },
            $filename,
            [
                'Content-Type' => $mimeType,
            ],
            'attachment'
        );
    }
}
