<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class S3Service
{
    protected ?S3Client $client = null;
    protected string $bucket;
    protected string $region;

    public function __construct()
    {
        $this->bucket = config('filesystems.disks.s3.bucket', '');
        $this->region = config('filesystems.disks.s3.region', 'us-east-1');

        $key = config('filesystems.disks.s3.key');
        $secret = config('filesystems.disks.s3.secret');

        if ($key && $secret && $this->bucket) {
            $this->client = new S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);
        }
    }

    /**
     * Check if S3 is configured.
     */
    public function isConfigured(): bool
    {
        return $this->client !== null && !empty($this->bucket);
    }

    /**
     * Upload a file to S3.
     */
    public function upload(
        UploadedFile $file,
        string $path = '',
        ?string $filename = null,
        string $visibility = 'private',
        array $metadata = []
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('S3 is not configured. Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_BUCKET.');
        }

        try {
            $filename = $filename ?? $this->generateFilename($file);
            $key = $this->buildKey($path, $filename);

            $options = [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => fopen($file->getRealPath(), 'rb'),
                'ContentType' => $file->getMimeType(),
                'ACL' => $this->getAcl($visibility),
            ];

            if (!empty($metadata)) {
                $options['Metadata'] = $metadata;
            }

            $result = $this->client->putObject($options);

            Log::info('S3 upload successful', [
                'key' => $key,
                'bucket' => $this->bucket,
            ]);

            return [
                'success' => true,
                'path' => $key,
                'url' => $result['ObjectURL'] ?? $this->getUrl($key),
                'storage' => 's3',
            ];

        } catch (AwsException $e) {
            Log::error('S3 upload failed', [
                'error' => $e->getMessage(),
                'key' => $key ?? 'unknown',
            ]);
            throw $e;
        }
    }

    /**
     * Upload from a string/base64 content.
     */
    public function uploadContent(
        string $content,
        string $path,
        string $filename,
        string $contentType,
        string $visibility = 'private'
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'S3 not configured'];
        }

        try {
            $key = $this->buildKey($path, $filename);

            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $content,
                'ContentType' => $contentType,
                'ACL' => $this->getAcl($visibility),
            ]);

            return [
                'success' => true,
                'path' => $key,
                'url' => $result['ObjectURL'] ?? $this->getUrl($key),
                'storage' => 's3',
            ];

        } catch (AwsException $e) {
            Log::error('S3 content upload failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete a file from S3.
     */
    public function delete(string $path): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);

            Log::info('S3 file deleted', ['path' => $path]);
            return true;

        } catch (AwsException $e) {
            Log::error('S3 delete failed', ['error' => $e->getMessage(), 'path' => $path]);
            return false;
        }
    }

    /**
     * Delete multiple files from S3.
     */
    public function deleteMultiple(array $paths): array
    {
        if (!$this->isConfigured() || empty($paths)) {
            return ['success' => false, 'deleted' => 0];
        }

        try {
            $objects = array_map(fn($path) => ['Key' => $path], $paths);

            $result = $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $objects,
                ],
            ]);

            $deletedCount = count($result['Deleted'] ?? []);
            Log::info('S3 bulk delete', ['deleted' => $deletedCount, 'requested' => count($paths)]);

            return [
                'success' => true,
                'deleted' => $deletedCount,
                'errors' => $result['Errors'] ?? [],
            ];

        } catch (AwsException $e) {
            Log::error('S3 bulk delete failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'deleted' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if a file exists in S3.
     */
    public function exists(string $path): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Get a file's public URL.
     */
    public function getUrl(string $path): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('S3 is not configured.');
        }

        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$path}";
    }

    /**
     * Generate a pre-signed URL for temporary access.
     */
    public function getSignedUrl(string $path, int $minutes = 60): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $command = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);

            $request = $this->client->createPresignedRequest(
                $command,
                "+{$minutes} minutes"
            );

            return (string) $request->getUri();

        } catch (AwsException $e) {
            Log::error('S3 signed URL generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate a pre-signed URL for uploading.
     */
    public function getUploadSignedUrl(
        string $path,
        string $contentType,
        int $minutes = 30,
        array $metadata = []
    ): ?array {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $path,
                'ContentType' => $contentType,
            ];

            if (!empty($metadata)) {
                $params['Metadata'] = $metadata;
            }

            $command = $this->client->getCommand('PutObject', $params);

            $request = $this->client->createPresignedRequest(
                $command,
                "+{$minutes} minutes"
            );

            return [
                'url' => (string) $request->getUri(),
                'path' => $path,
                'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
                'headers' => [
                    'Content-Type' => $contentType,
                ],
            ];

        } catch (AwsException $e) {
            Log::error('S3 upload signed URL generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Copy a file within S3.
     */
    public function copy(string $sourcePath, string $destinationPath): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => "{$this->bucket}/{$sourcePath}",
                'Key' => $destinationPath,
            ]);

            return true;

        } catch (AwsException $e) {
            Log::error('S3 copy failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Move a file within S3.
     */
    public function move(string $sourcePath, string $destinationPath): bool
    {
        if ($this->copy($sourcePath, $destinationPath)) {
            return $this->delete($sourcePath);
        }
        return false;
    }

    /**
     * Get file metadata.
     */
    public function getMetadata(string $path): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);

            return [
                'content_type' => $result['ContentType'] ?? null,
                'content_length' => $result['ContentLength'] ?? null,
                'last_modified' => $result['LastModified'] ?? null,
                'metadata' => $result['Metadata'] ?? [],
            ];

        } catch (AwsException $e) {
            return null;
        }
    }

    /**
     * List files in a directory.
     */
    public function listFiles(string $prefix = '', int $maxKeys = 1000): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $result = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => $maxKeys,
            ]);

            $files = [];
            foreach ($result['Contents'] ?? [] as $object) {
                $files[] = [
                    'path' => $object['Key'],
                    'size' => $object['Size'],
                    'last_modified' => $object['LastModified']->format('Y-m-d H:i:s'),
                ];
            }

            return $files;

        } catch (AwsException $e) {
            Log::error('S3 list files failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Set file ACL/permissions.
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->client->putObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'ACL' => $this->getAcl($visibility),
            ]);

            return true;

        } catch (AwsException $e) {
            Log::error('S3 set visibility failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate a unique filename.
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Build the S3 key from path and filename.
     */
    protected function buildKey(string $path, string $filename): string
    {
        return $path ? "{$path}/{$filename}" : $filename;
    }

    /**
     * Convert visibility to S3 ACL.
     */
    protected function getAcl(string $visibility): string
    {
        return match ($visibility) {
            'public' => 'public-read',
            'private' => 'private',
            default => 'private',
        };
    }

    // ===== Event-specific helpers =====

    /**
     * Upload event photo.
     */
    public function uploadEventPhoto(int $eventId, UploadedFile $file): array
    {
        return $this->upload(
            $file,
            "events/{$eventId}/photos",
            null,
            'public',
            ['event_id' => (string) $eventId, 'type' => 'photo']
        );
    }

    /**
     * Upload user avatar.
     */
    public function uploadAvatar(int $userId, UploadedFile $file): array
    {
        return $this->upload(
            $file,
            "users/{$userId}/avatar",
            'avatar.' . $file->getClientOriginalExtension(),
            'public',
            ['user_id' => (string) $userId, 'type' => 'avatar']
        );
    }

    /**
     * Upload event document.
     */
    public function uploadEventDocument(int $eventId, UploadedFile $file): array
    {
        return $this->upload(
            $file,
            "events/{$eventId}/documents",
            null,
            'private',
            ['event_id' => (string) $eventId, 'type' => 'document']
        );
    }

    /**
     * Get signed URL for event photo.
     */
    public function getEventPhotoUrl(string $path, int $minutes = 60): ?string
    {
        return $this->getSignedUrl($path, $minutes);
    }

    /**
     * Delete all event files.
     */
    public function deleteEventFiles(int $eventId): array
    {
        $prefix = "events/{$eventId}/";
        $files = $this->listFiles($prefix);
        $paths = array_column($files, 'path');

        if (empty($paths)) {
            return ['success' => true, 'deleted' => 0];
        }

        return $this->deleteMultiple($paths);
    }
}
