<?php

namespace App\Services;

use App\Enums\PhotoType;
use App\Helpers\StorageHelper;
use App\Jobs\ProcessPhotoJob;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PhotoService
{
    /**
     * Upload a single photo.
     */
    public function upload(Event $event, UploadedFile $file, User $user, string $type = 'event_photo', ?string $description = null): Photo
    {
        $path = $this->storeFile($event, $file);
        $url = StorageHelper::url($path);
        
        $photo = $event->photos()->create([
            'uploaded_by_user_id' => $user->id,
            'type' => $type,
            'url' => $url,
            'thumbnail_url' => $url, // Will be updated by job
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
        // Vérifier que le fichier est valide
        if (!$file->isValid()) {
            throw new \RuntimeException('Le fichier uploadé n\'est pas valide.');
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "events/{$event->id}/photos/{$filename}";
        $directory = dirname($path);

        try {
            $disk = StorageHelper::disk();
            // Ne pas envoyer d'ACL si le bucket n'autorise pas les ACLs (Block Public Access)
            $disk->put($path, file_get_contents($file->getRealPath()));
        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            Log::error('Photo storage failed', [
                'event_id' => $event->id,
                'path' => $path,
                'error' => $e->getMessage(),
                'aws_error' => $previous ? $previous->getMessage() : null,
                'aws_exception' => $previous ? get_class($previous) : null,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Impossible d’enregistrer la photo. Veuillez réessayer.', 0, $e);
        }

        return $path;
    }

    /**
     * Generate thumbnail for a photo.
     */
    public function generateThumbnail(string $sourcePath, int $width = 300, int $height = 300): ?string
    {
        $disk = StorageHelper::disk();

        if (!$disk->exists($sourcePath)) {
            return null;
        }

        $pathInfo = pathinfo($sourcePath);
        $thumbnailPath = $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['basename'];

        // Check if intervention/image is available
        $imageManagerClass = 'Intervention\Image\ImageManager';
        if (!class_exists($imageManagerClass)) {
            $disk->copy($sourcePath, $thumbnailPath);
            return $thumbnailPath;
        }

        try {
            $content = $disk->get($sourcePath);
            $tempPath = storage_path('app/temp/' . Str::uuid() . '.' . ($pathInfo['extension'] ?? 'jpg'));
            $tempDir = dirname($tempPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            file_put_contents($tempPath, $content);

            $driverClass = 'Intervention\Image\Drivers\Gd\Driver';
            $manager = new $imageManagerClass(new $driverClass());
            $image = $manager->read($tempPath);
            $image->cover($width, $height);

            $thumbnailTempPath = $tempDir . '/thumb_' . basename($tempPath);
            $image->save($thumbnailTempPath);

            $thumbnailContent = file_get_contents($thumbnailTempPath);
            $disk->put($thumbnailPath, $thumbnailContent);

            @unlink($tempPath);
            @unlink($thumbnailTempPath);

            return $thumbnailPath;
        } catch (\Exception $e) {
            $disk->copy($sourcePath, $thumbnailPath);
            return $thumbnailPath;
        }
    }

    /**
     * Compress a photo.
     */
    public function compress(string $sourcePath, int $quality = 80): bool
    {
        $disk = StorageHelper::disk();

        if (!$disk->exists($sourcePath)) {
            return false;
        }

        $imageManagerClass = 'Intervention\Image\ImageManager';
        if (!class_exists($imageManagerClass)) {
            return true;
        }

        try {
            $content = $disk->get($sourcePath);
            $pathInfo = pathinfo($sourcePath);
            $tempPath = storage_path('app/temp/' . Str::uuid() . '.' . ($pathInfo['extension'] ?? 'jpg'));
            $tempDir = dirname($tempPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            file_put_contents($tempPath, $content);

            $driverClass = 'Intervention\Image\Drivers\Gd\Driver';
            $manager = new $imageManagerClass(new $driverClass());
            $image = $manager->read($tempPath);
            $image->save($tempPath, $quality);

            $disk->put($sourcePath, file_get_contents($tempPath));
            @unlink($tempPath);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a photo and its files.
     * If the photo is the cover photo, remove cover_photo_id from the event.
     */
    public function delete(Photo $photo): bool
    {
        $event = $photo->event;
        $isCoverPhoto = $event->cover_photo_id === $photo->id;

        $mainPath = StorageHelper::urlToPath($photo->url);
        $thumbnailPath = StorageHelper::urlToPath($photo->thumbnail_url);

        $mainDisk = StorageHelper::diskForUrl($photo->url);
        $thumbDisk = StorageHelper::diskForUrl($photo->thumbnail_url);

        if ($mainPath && $mainDisk->exists($mainPath)) {
            $mainDisk->delete($mainPath);
        }

        if ($thumbnailPath && $thumbnailPath !== $mainPath && $thumbDisk->exists($thumbnailPath)) {
            $thumbDisk->delete($thumbnailPath);
        }

        // Delete the photo record
        $photo->delete();

        // If it was the cover photo, remove cover_photo_id from the event
        if ($isCoverPhoto) {
            $event->update(['cover_photo_id' => null]);
        }

        return true;
    }


    /**
     * Toggle featured status.
     * If the photo becomes featured, it becomes the cover photo.
     * If it's unfeatured and it was the cover photo, remove cover_photo_id.
     */
    public function toggleFeatured(Photo $photo): Photo
    {
        $wasFeatured = $photo->is_featured;
        $photo->update(['is_featured' => !$photo->is_featured]);
        $photo->refresh();

        $event = $photo->event;

        if ($photo->is_featured) {
            // Photo became featured: set as cover photo and unfeature others
            $event->photos()
                ->where('id', '!=', $photo->id)
                ->where('is_featured', true)
                ->update(['is_featured' => false]);
            
            $event->update(['cover_photo_id' => $photo->id]);
        } else {
            // Photo is no longer featured: if it was the cover photo, remove it
            if ($event->cover_photo_id === $photo->id) {
                $event->update(['cover_photo_id' => null]);
            }
        }

        return $photo->fresh();
    }

    /**
     * Set a photo as the only featured photo for an event.
     * This also sets it as the cover photo.
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

        // Set as cover photo in the event
        $photo->event->update(['cover_photo_id' => $photo->id]);

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
     * Uses "maximum généreux" approach: get effective limit using MAX between
     * stored event limit and current account subscription limit.
     */
    public function getMaxPhotos(Event $event): int
    {
        $entitlementService = app(EntitlementService::class);
        
        // Get effective limit using MAX between stored and current subscription
        $effectiveLimit = $entitlementService->getEffectiveLimit(
            $event,
            $event->user,
            'photos.max_per_event'
        );

        // -1 means unlimited, return PHP_INT_MAX for compatibility
        return $effectiveLimit === -1 ? PHP_INT_MAX : $effectiveLimit;
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

    /**
     * Validate photo upload token and return the guest.
     */
    public function validatePhotoUploadToken(Event $event, string $token): ?\App\Models\Guest
    {
        $guest = \App\Models\Guest::where('event_id', $event->id)
            ->where('photo_upload_token', $token)
            ->where('checked_in', true)
            ->first();

        return $guest;
    }

    /**
     * Upload photos publicly (without authentication).
     */
    public function uploadPublic(Event $event, array $files, string $token, ?string $guestName = null): Collection
    {
        // Validate token
        $guest = $this->validatePhotoUploadToken($event, $token);
        if (!$guest) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => 'Token invalide ou invité non vérifié.'], 403)
            );
        }

        $photos = collect();

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $path = $this->storeFile($event, $file);
                $url = StorageHelper::url($path);

                // Create photo without user (public upload)
                $photo = $event->photos()->create([
                    'uploaded_by_user_id' => null, // Public upload, no user
                    'type' => 'event_photo',
                    'url' => $url,
                    'thumbnail_url' => $url, // Will be updated by job
                    'description' => $guestName ? "Uploadé par {$guestName}" : 'Uploadé par un invité',
                    'is_featured' => false,
                ]);

                // Dispatch job for async processing
                ProcessPhotoJob::dispatch($photo, $path);

                $photos->push($photo);
            }
        }

        return $photos;
    }

    /**
     * Download multiple photos as a ZIP archive.
     */
    public function downloadMultiple(Event $event, array $photoIds): string
    {
        $photos = $event->photos()->whereIn('id', $photoIds)->get();

        if ($photos->isEmpty()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => 'Aucune photo trouvée.'], 404)
            );
        }

        // Create temporary ZIP file
        $zipPath = storage_path('app/temp/' . Str::uuid() . '.zip');
        $zipDir = dirname($zipPath);

        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Impossible de créer le fichier ZIP.');
        }

        foreach ($photos as $photo) {
            $path = StorageHelper::urlToPath($photo->url);
            $disk = StorageHelper::diskForUrl($photo->url);
            if ($path && $disk->exists($path)) {
                $fileContent = $disk->get($path);
                // Generate filename from photo ID and extension
                $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
                $filename = 'photo-' . $photo->id . '.' . $extension;
                $zip->addFromString($filename, $fileContent);
            }
        }

        $zip->close();

        return $zipPath;
    }
}
