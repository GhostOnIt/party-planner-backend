<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Get the Storage disk for uploads (S3 only).
     */
    public static function disk()
    {
        return Storage::disk('s3');
    }

    /**
     * Get the public URL for a stored file path.
     * Uses CDN_URL when set (e.g. CloudFront), otherwise falls back to S3 direct URL.
     */
    public static function url(string $path): string
    {
        $cdnUrl = config('filesystems.disks.s3.cdn_url');

        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        return self::disk()->url($path);
    }

    /**
     * Convert a stored URL back to the S3 key.
     * Handles CDN URLs, S3 URLs and legacy /storage/ paths (extracts key for delete ops).
     */
    public static function urlToPath(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path) {
                // CDN URL (e.g. CloudFront): https://cdn.net/events/xxx/photos/yyy.jpg -> events/xxx/photos/yyy.jpg
                $cdnUrl = config('filesystems.disks.s3.cdn_url');
                if ($cdnUrl) {
                    $cdnBase = rtrim($cdnUrl, '/');
                    if (str_starts_with($url, $cdnBase)) {
                        return ltrim($path, '/');
                    }
                }

                // S3 virtual-hosted style: path may include bucket
                $bucket = config('filesystems.disks.s3.bucket');
                if ($bucket && str_starts_with($path, '/' . $bucket . '/')) {
                    return ltrim(substr($path, strlen($bucket) + 2), '/');
                }
                return ltrim($path, '/');
            }
        }

        return preg_replace('#^/storage/#', '', $url) ?: null;
    }

    /**
     * Get the disk for a file URL (always S3).
     */
    public static function diskForUrl(?string $url)
    {
        return self::disk();
    }
}
