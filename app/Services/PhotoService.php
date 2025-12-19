<?php

namespace App\Services;

use App\Enums\PhotoType;
use App\Jobs\ProcessPhotoJob;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoService
{
    /**
     * Upload a single photo.
     */
    public function upload(Event $event, UploadedFile $file, User $user, string $type = 'event_photo', ?string $description = null): Photo
    {
        $path = $this->storeFile($event, $file);

        $photo = $event->photos()->create([
            'uploaded_by_user_id' => $user->id,
            'type' => $type,
            'url' => Storage::url($path),
            'thumbnail_url' => Storage::url($path), // Will be updated by job
            'description' => $description,
            'is_featured' => false,
        ]);

        // Dispatch job for async processing (thumbnail generation, compression)
        ProcessPhotoJob::dispatch($photo, $path);

        return $photo;
    }

    /**
     * Upload multiple photos.
     */
    public function uploadMultiple(Event $event, array $files, User $user, string $type = 'event_photo', ?string $description = null): Collection
    {
        $photos = collect();

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $photos->push($this->upload($event, $file, $user, $type, $description));
            }
        }

        return $photos;
    }

    /**
     * Store file to disk.
     */
    protected function storeFile(Event $event, UploadedFile $file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "events/{$event->id}/photos/{$filename}";

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Generate thumbnail for a photo.
     */
    public function generateThumbnail(string $sourcePath, int $width = 300, int $height = 300): ?string
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($sourcePath)) {
            return null;
        }

        // Get the directory and filename
        $pathInfo = pathinfo($sourcePath);
        $thumbnailPath = $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['basename'];

        // Ensure thumbnail directory exists
        $thumbnailDir = $pathInfo['dirname'] . '/thumbnails';
        if (!$disk->exists($thumbnailDir)) {
            $disk->makeDirectory($thumbnailDir);
        }

        // Check if intervention/image is available
        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            // Fallback: copy original as thumbnail
            $disk->copy($sourcePath, $thumbnailPath);
            return $thumbnailPath;
        }

        try {
            // Use Intervention Image if available
            $manager = new \Intervention\Image\ImageManager(
                new \Intervention\Image\Drivers\Gd\Driver()
            );

            $image = $manager->read($disk->path($sourcePath));
            $image->cover($width, $height);
            $image->save($disk->path($thumbnailPath));

            return $thumbnailPath;
        } catch (\Exception $e) {
            // Fallback: copy original as thumbnail
            $disk->copy($sourcePath, $thumbnailPath);
            return $thumbnailPath;
        }
    }

    /**
     * Compress a photo.
     */
    public function compress(string $sourcePath, int $quality = 80): bool
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($sourcePath)) {
            return false;
        }

        // Check if intervention/image is available
        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            return true; // Skip compression if library not available
        }

        try {
            $manager = new \Intervention\Image\ImageManager(
                new \Intervention\Image\Drivers\Gd\Driver()
            );

            $image = $manager->read($disk->path($sourcePath));
            $image->save($disk->path($sourcePath), $quality);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a photo and its files.
     */
    public function delete(Photo $photo): bool
    {
        // Extract paths from URLs
        $mainPath = $this->urlToPath($photo->url);
        $thumbnailPath = $this->urlToPath($photo->thumbnail_url);

        $disk = Storage::disk('public');

        // Delete main file
        if ($mainPath && $disk->exists($mainPath)) {
            $disk->delete($mainPath);
        }

        // Delete thumbnail (if different from main)
        if ($thumbnailPath && $thumbnailPath !== $mainPath && $disk->exists($thumbnailPath)) {
            $disk->delete($thumbnailPath);
        }

        return $photo->delete();
    }

    /**
     * Convert storage URL to path.
     */
    protected function urlToPath(string $url): ?string
    {
        // Remove /storage/ prefix to get the path
        $path = str_replace('/storage/', '', $url);

        return $path ?: null;
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Photo $photo): Photo
    {
        $photo->update(['is_featured' => !$photo->is_featured]);

        return $photo->fresh();
    }

    /**
     * Set a photo as the only featured photo for an event.
     */
    public function setAsFeatured(Photo $photo): Photo
    {
        // Unfeature all other photos of the event
        $photo->event->photos()
            ->where('id', '!=', $photo->id)
            ->where('is_featured', true)
            ->update(['is_featured' => false]);

        // Feature this photo
        $photo->update(['is_featured' => true]);

        return $photo->fresh();
    }

    /**
     * Get photos by type.
     */
    public function getByType(Event $event, string $type): Collection
    {
        return $event->photos()
            ->where('type', $type)
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get moodboard photos.
     */
    public function getMoodboard(Event $event): Collection
    {
        return $this->getByType($event, 'moodboard');
    }

    /**
     * Get event photos.
     */
    public function getEventPhotos(Event $event): Collection
    {
        return $this->getByType($event, 'event_photo');
    }

    /**
     * Get featured photos.
     */
    public function getFeatured(Event $event): Collection
    {
        return $event->photos()
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Update photo description.
     */
    public function updateDescription(Photo $photo, ?string $description): Photo
    {
        $photo->update(['description' => $description]);

        return $photo->fresh();
    }

    /**
     * Update photo type.
     */
    public function updateType(Photo $photo, string $type): Photo
    {
        $photo->update(['type' => $type]);

        return $photo->fresh();
    }

    /**
     * Get statistics for an event's gallery.
     */
    public function getStatistics(Event $event): array
    {
        $photos = $event->photos;

        return [
            'total' => $photos->count(),
            'moodboard' => $photos->where('type', 'moodboard')->count(),
            'event_photos' => $photos->where('type', 'event_photo')->count(),
            'featured' => $photos->where('is_featured', true)->count(),
        ];
    }

    /**
     * Check if user can add more photos based on subscription.
     */
    public function canAddPhotos(Event $event, int $count = 1): bool
    {
        $currentCount = $event->photos()->count();
        $maxPhotos = $this->getMaxPhotos($event);

        return ($currentCount + $count) <= $maxPhotos;
    }

    /**
     * Get maximum photos allowed.
     */
    public function getMaxPhotos(Event $event): int
    {
        $subscription = $event->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return config('partyplanner.free_tier.max_photos', 5);
        }

        // Pro plans have unlimited photos
        return $subscription->isPro() ? PHP_INT_MAX : config('partyplanner.plans.starter.max_photos', 50);
    }

    /**
     * Get remaining photo slots.
     */
    public function getRemainingSlots(Event $event): int
    {
        $max = $this->getMaxPhotos($event);
        $current = $event->photos()->count();

        return max(0, $max - $current);
    }

    /**
     * Bulk delete photos.
     */
    public function bulkDelete(Event $event, array $photoIds): int
    {
        $photos = $event->photos()->whereIn('id', $photoIds)->get();
        $deleted = 0;

        foreach ($photos as $photo) {
            if ($this->delete($photo)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Move photos between types.
     */
    public function bulkUpdateType(Event $event, array $photoIds, string $type): int
    {
        return $event->photos()
            ->whereIn('id', $photoIds)
            ->update(['type' => $type]);
    }
}
