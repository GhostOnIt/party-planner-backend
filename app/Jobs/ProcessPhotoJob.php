<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Photo $photo,
        public string $sourcePath
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PhotoService $photoService): void
    {
        // Verify photo still exists
        if (!$this->photo->exists) {
            Log::warning("ProcessPhotoJob: Photo {$this->photo->id} no longer exists");
            return;
        }

        // Verify source file exists
        if (!Storage::disk('public')->exists($this->sourcePath)) {
            Log::warning("ProcessPhotoJob: Source file {$this->sourcePath} not found");
            return;
        }

        // Get thumbnail dimensions from config
        $width = config('partyplanner.uploads.photos.thumbnail_width', 300);
        $height = config('partyplanner.uploads.photos.thumbnail_height', 300);

        // Generate thumbnail
        $thumbnailPath = $photoService->generateThumbnail($this->sourcePath, $width, $height);

        if ($thumbnailPath) {
            $this->photo->update([
                'thumbnail_url' => Storage::url($thumbnailPath),
            ]);

            Log::info("ProcessPhotoJob: Generated thumbnail for photo {$this->photo->id}");
        }

        // Compress original photo
        $quality = config('partyplanner.uploads.photos.compression_quality', 80);
        $compressed = $photoService->compress($this->sourcePath, $quality);

        if ($compressed) {
            Log::info("ProcessPhotoJob: Compressed photo {$this->photo->id}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessPhotoJob failed for photo {$this->photo->id}: {$exception->getMessage()}");
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'photo-processing',
            'photo:' . $this->photo->id,
            'event:' . $this->photo->event_id,
        ];
    }
}
