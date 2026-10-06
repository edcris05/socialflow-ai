<?php

namespace Tests\Feature;

use App\Contracts\PublicMediaStorageInterface;
use App\Models\PublicationMedia;
use App\Services\Publishing\LaravelFilesystemPublicMediaStorage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudflareR2ConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_r2_disk_uses_the_s3_driver_without_object_visibility_acl(): void
    {
        $disk = config('filesystems.disks.r2');
        $constraints = config('filesystems.public_media_disk_constraints.r2');

        $this->assertIsArray($disk);
        $this->assertSame('s3', $disk['driver']);
        $this->assertSame('auto', $disk['region']);
        $this->assertFalse($disk['use_path_style_endpoint']);
        $this->assertFalse($disk['throw']);
        $this->assertFalse($disk['report']);
        $this->assertArrayHasKey('key', $disk);
        $this->assertArrayHasKey('secret', $disk);
        $this->assertArrayHasKey('bucket', $disk);
        $this->assertArrayHasKey('endpoint', $disk);
        $this->assertArrayHasKey('url', $disk);
        $this->assertArrayNotHasKey('visibility', $disk);
        $this->assertSame(
            ['key', 'secret', 'region', 'bucket', 'endpoint', 'url'],
            $constraints['required'],
        );
        $this->assertSame([['endpoint', 'url']], $constraints['distinct_host_pairs']);
    }

    public function test_public_media_disk_default_remains_disabled_when_environment_value_is_absent(): void
    {
        $environment = Env::getRepository();
        $hadValue = $environment->has('PUBLIC_MEDIA_DISK');
        $previousValue = $environment->get('PUBLIC_MEDIA_DISK');
        $environment->clear('PUBLIC_MEDIA_DISK');

        try {
            $filesystems = require config_path('filesystems.php');

            $this->assertNull($filesystems['public_media_disk']);
        } finally {
            if ($hadValue && $previousValue !== null) {
                $environment->set('PUBLIC_MEDIA_DISK', $previousValue);
            }
        }
    }

    public function test_disabled_public_media_disk_requires_no_r2_configuration(): void
    {
        Config::set('filesystems.public_media_disk', null);
        foreach (['key', 'secret', 'region', 'bucket', 'endpoint', 'url'] as $key) {
            Config::set('filesystems.disks.r2.'.$key, null);
        }
        Config::set('services.meta.publishing_enabled', false);
        Config::set('services.scheduled_publishing.enabled', false);
        Http::preventStrayRequests();

        $storage = $this->app->make(PublicMediaStorageInterface::class);
        $media = new PublicationMedia(['public_url' => 'https://manual.example.com/image.jpg']);
        $media->setRelation('publicHosting', null);
        $this->artisan('socialflow:publish-due', ['--limit' => 25])
            ->expectsOutputToContain('examined=0 published=0 skipped=0 blocked=0 failed=0 outcome_unknown=0')
            ->assertSuccessful();

        $this->assertInstanceOf(LaravelFilesystemPublicMediaStorage::class, $storage);
        $this->assertNull(config('filesystems.public_media_disk'));
        $this->assertSame('https://manual.example.com/image.jpg', $media->effectivePublicUrl());
        Http::assertNothingSent();
    }

    public function test_r2_endpoint_and_public_url_are_mapped_separately_without_network_access(): void
    {
        $environment = Env::getRepository();
        $values = [
            'R2_ACCESS_KEY_ID' => 'test-access-key',
            'R2_SECRET_ACCESS_KEY' => 'test-secret-key',
            'R2_BUCKET' => 'test-public-media',
            'R2_ENDPOINT' => 'https://api-storage.example.com',
            'R2_URL' => 'https://public-media.example.com',
            'R2_REGION' => 'auto',
        ];
        $previous = [];

        foreach ($values as $key => $value) {
            $previous[$key] = [
                'exists' => $environment->has($key),
                'value' => $environment->get($key),
            ];
            $environment->set($key, $value);
        }

        try {
            $filesystems = require config_path('filesystems.php');
            Config::set('filesystems.disks.r2', $filesystems['disks']['r2']);
            Storage::forgetDisk('r2');

            $this->assertSame('https://api-storage.example.com', $filesystems['disks']['r2']['endpoint']);
            $this->assertSame('https://public-media.example.com', $filesystems['disks']['r2']['url']);
            $this->assertSame(
                'https://public-media.example.com/publication-media/test.jpg',
                Storage::disk('r2')->url('publication-media/test.jpg'),
            );
        } finally {
            Storage::forgetDisk('r2');

            foreach ($previous as $key => $state) {
                if ($state['exists'] && $state['value'] !== null) {
                    $environment->set($key, $state['value']);
                } else {
                    $environment->clear($key);
                }
            }
        }
    }
}
