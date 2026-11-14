<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Get the Storage disk for uploads (S3 only).
     *
     * @return \Illuminate\Filesystem\FilesystemAdapter
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

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($path);
    }

    /** Generate fresh read URLs at serialization time; never persist signatures. */
    public static function photoReadUrl(?string $url): ?string
    {
        if (!$url || !app()->environment('staging') || !config('staging.signed_photo_urls', false)) {
            return $url;
        }

        $endpointHost = strtolower((string) parse_url((string) config('filesystems.disks.s3.endpoint'), PHP_URL_HOST));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $bucket = strtolower((string) config('filesystems.disks.s3.bucket'));
        if (!$endpointHost || !in_array($host, [$endpointHost, $bucket . '.' . $endpointHost], true)) {
            return $url;
        }

        $path = self::urlToPath($url);
        if (!$path || !preg_match('#^events/[^/]+/photos/#', $path)) {
            return $url;
        }

        return self::disk()->temporaryUrl($path, now()->addHour());
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
                $key = ltrim($path, '/');
                return str_starts_with($key, 'events/') ? $key : null;
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
