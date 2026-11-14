<?php

namespace Tests\Unit\Helpers;

use App\Helpers\StorageHelper;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class StorageHelperTest extends TestCase
{
    public function test_signs_only_photo_urls_from_the_staging_bucket(): void
    {
        $this->app->instance('env', 'staging');
        config(['staging.signed_photo_urls' => true, 'filesystems.disks.s3.endpoint' => 'https://t3.storageapi.dev', 'filesystems.disks.s3.bucket' => 'test-bucket']);
        $disk = Mockery::mock(FilesystemAdapter::class);
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        $disk->shouldReceive('temporaryUrl')->once()->with('events/event-id/photos/test.png', Mockery::type(\DateTimeInterface::class))->andReturn('https://signed.example/photo');

        $this->assertSame('https://signed.example/photo', StorageHelper::photoReadUrl('https://test-bucket.t3.storageapi.dev/events/event-id/photos/test.png'));
        $this->assertSame('https://foreign.example/events/event-id/photos/test.png', StorageHelper::photoReadUrl('https://foreign.example/events/event-id/photos/test.png'));
        $this->assertSame('https://test-bucket.t3.storageapi.dev/activity-logs/private.json', StorageHelper::photoReadUrl('https://test-bucket.t3.storageapi.dev/activity-logs/private.json'));
    }

    public function test_production_urls_remain_unchanged_even_if_option_is_enabled(): void
    {
        $this->app->instance('env', 'production');
        config(['staging.signed_photo_urls' => true]);
        Storage::shouldReceive('disk')->never();
        $url = 'https://test-bucket.t3.storageapi.dev/events/event-id/photos/test.png';
        $this->assertSame($url, StorageHelper::photoReadUrl($url));
    }

    public function test_signing_is_disabled_by_default(): void
    {
        $this->app->instance('env', 'staging');
        config(['staging.signed_photo_urls' => false]);
        Storage::shouldReceive('disk')->never();
        $url = 'https://test-bucket.t3.storageapi.dev/events/event-id/photos/test.png';
        $this->assertSame($url, StorageHelper::photoReadUrl($url));
        $this->assertNull(StorageHelper::photoReadUrl(null));
    }
}
