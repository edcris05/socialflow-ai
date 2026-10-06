<?php

namespace Tests\Feature;

use App\Contracts\PublicHostResolverInterface;
use App\Contracts\PublicMediaStorageInterface;
use App\Models\Brand;
use App\Models\Draft;
use App\Models\PublicationMedia;
use App\Models\PublicMediaHosting;
use App\Models\User;
use App\Services\Publishing\PublicMediaStorageResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PublicMediaHostingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MANAGED_BASE_URL = 'https://media.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Config::set('filesystems.public_media_disk', 'public-media');
        Config::set('filesystems.disks.public-media', [
            'driver' => 'local',
            'url' => self::MANAGED_BASE_URL,
            'visibility' => 'public',
        ]);
        Storage::fake('public-media', [
            'url' => self::MANAGED_BASE_URL,
            'visibility' => 'public',
        ]);
        Http::preventStrayRequests();
    }

    public function test_approved_jpeg_is_streamed_to_public_storage_with_auditable_metadata(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia([
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => 'https://manual.example.com/image.jpg',
        ]);
        $content = "\xFF\xD8\xFFpublished-jpeg";
        Storage::disk('local')->put($media->storage_path, $content);

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_HOSTED, $hosting->status);
        $this->assertSame('local', $hosting->provider);
        $this->assertSame('public-media', $hosting->disk);
        $this->assertSame('image/jpeg', $hosting->content_type);
        $this->assertSame(strlen($content), $hosting->size_bytes);
        $this->assertSame(hash('sha256', $content), $hosting->checksum_sha256);
        $this->assertNotNull($hosting->hosted_at);
        $this->assertStringStartsWith('publication-media/'.$brand->getKey().'/'.$draft->getKey().'/'.$media->getKey().'/', $hosting->object_key);
        $this->assertStringEndsWith('.jpg', $hosting->object_key);
        $this->assertStringNotContainsString('original.jpg', $hosting->object_key);
        $this->assertStringNotContainsString('..', $hosting->object_key);
        $this->assertSame(self::MANAGED_BASE_URL.'/'.$hosting->object_key, $hosting->public_url);
        Storage::disk('local')->assertExists($media->storage_path);
        Storage::disk('public-media')->assertExists($hosting->object_key);
        $this->assertSame('public', Storage::disk('public-media')->getVisibility($hosting->object_key));
        $media->refresh();
        $this->assertSame(PublicationMedia::PREFLIGHT_NOT_CHECKED, $media->preflight_status);
        $this->assertNull($media->preflight_checked_at);
        $this->assertFalse($media->isPublishable());
        Http::assertNothingSent();
    }

    public function test_r2_disk_uses_streamed_storage_and_the_public_base_url_without_object_acl(): void
    {
        $publicBaseUrl = 'https://pub-test.r2.dev';
        $apiEndpoint = 'https://account-id.r2.cloudflarestorage.com';
        $this->configureFakeR2($publicBaseUrl, $apiEndpoint);
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        $content = "\xFF\xD8\xFFr2-jpeg";
        Storage::disk('local')->put($media->storage_path, $content);

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_HOSTED, $hosting->status);
        $this->assertSame('s3', $hosting->provider);
        $this->assertSame('r2', $hosting->disk);
        $this->assertStringStartsWith($publicBaseUrl.'/publication-media/', $hosting->public_url);
        $this->assertStringNotContainsString('r2.cloudflarestorage.com', $hosting->public_url);
        $this->assertSame(hash('sha256', $content), $hosting->checksum_sha256);
        $this->assertStringNotContainsString($media->original_filename, $hosting->object_key);
        $this->assertSame('private', Storage::disk('r2')->getVisibility($hosting->object_key));
        Storage::disk('local')->assertExists($media->storage_path);
        Storage::disk('r2')->assertExists($hosting->object_key);
        Http::assertNothingSent();
    }

    public function test_generic_s3_compatible_disk_may_share_endpoint_and_public_url_host(): void
    {
        $publicBaseUrl = 'https://storage.example.com/public';
        Config::set('filesystems.public_media_disk', 's3-compatible');
        Config::set('filesystems.disks.s3-compatible', [
            'driver' => 's3',
            'key' => 'test-access-key',
            'secret' => 'test-secret-key',
            'region' => 'test-region',
            'bucket' => 'test-public-media',
            'url' => $publicBaseUrl,
            'endpoint' => 'https://storage.example.com',
            'use_path_style_endpoint' => false,
            'throw' => false,
            'report' => false,
        ]);
        Storage::fake('s3-compatible', [
            'url' => $publicBaseUrl,
            'visibility' => 'private',
        ]);
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_HOSTED, $hosting->status);
        $this->assertSame('s3', $hosting->provider);
        $this->assertSame('s3-compatible', $hosting->disk);
        $this->assertStringStartsWith($publicBaseUrl.'/publication-media/', $hosting->public_url);
        Storage::disk('s3-compatible')->assertExists($hosting->object_key);
        Http::assertNothingSent();
    }

    #[DataProvider('missingR2Configuration')]
    public function test_incomplete_r2_configuration_fails_before_storage_access(string $missingKey): void
    {
        $this->configureFakeR2();
        Config::set('filesystems.disks.r2.'.$missingKey, null);
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertSessionHas('error', 'El almacenamiento público no está configurado.');

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_FAILED, $hosting->status);
        $this->assertSame('PUBLIC_MEDIA_DISK_NOT_CONFIGURED', $hosting->last_error_code);
        $this->assertNull($hosting->hosted_at);
        $this->assertNull($hosting->public_url);
        $this->assertSame('El almacenamiento público no está configurado.', $hosting->last_error_message);
        Storage::disk('local')->assertExists($media->storage_path);
        $this->assertSame([], Storage::disk('r2')->allFiles());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function missingR2Configuration(): array
    {
        return [
            'access key' => ['key'],
            'secret key' => ['secret'],
            'region' => ['region'],
            'bucket' => ['bucket'],
            'api endpoint' => ['endpoint'],
            'public base URL' => ['url'],
        ];
    }

    #[DataProvider('invalidR2Urls')]
    public function test_invalid_r2_endpoint_or_public_url_fails_without_upload(
        string $publicBaseUrl,
        string $apiEndpoint,
        string $expectedCode,
    ): void {
        $this->configureFakeR2($publicBaseUrl, $apiEndpoint);
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertRedirect();

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_FAILED, $hosting->status);
        $this->assertSame($expectedCode, $hosting->last_error_code);
        $this->assertNull($hosting->public_url);
        $this->assertSame([], Storage::disk('r2')->allFiles());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidR2Urls(): array
    {
        return [
            'public URL is HTTP' => [
                'http://pub-test.r2.dev',
                'https://account-id.r2.cloudflarestorage.com',
                'PUBLIC_MEDIA_URL_INVALID',
            ],
            'public URL embeds credentials' => [
                'https://user:password@pub-test.r2.dev',
                'https://account-id.r2.cloudflarestorage.com',
                'PUBLIC_MEDIA_URL_INVALID',
            ],
            'public URL contains a query secret' => [
                'https://pub-test.r2.dev?signature=test-value',
                'https://account-id.r2.cloudflarestorage.com',
                'PUBLIC_MEDIA_URL_INVALID',
            ],
            'API endpoint is used as public URL' => [
                'https://account-id.r2.cloudflarestorage.com',
                'https://account-id.r2.cloudflarestorage.com',
                'PUBLIC_MEDIA_URL_INVALID',
            ],
            'API endpoint is not HTTPS' => [
                'https://pub-test.r2.dev',
                'http://account-id.r2.cloudflarestorage.com',
                'PUBLIC_MEDIA_STORAGE_CONFIG_INVALID',
            ],
        ];
    }

    #[DataProvider('storageExceptionStages')]
    public function test_r2_storage_exceptions_are_sanitized_and_persist_failed_state(string $stage): void
    {
        $this->configureFakeR2();
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");
        $sourceDisk = Storage::disk('local');
        $publicDisk = Mockery::mock();

        if ($stage === 'upload') {
            $publicDisk->shouldReceive('put')
                ->once()
                ->andThrow(new RuntimeException('test-only credential must not persist'));
        } else {
            $publicDisk->shouldReceive('put')->once()->andReturn(true);
            $publicDisk->shouldReceive('url')
                ->once()
                ->andThrow(new RuntimeException('test-only credential must not persist'));
        }

        Storage::shouldReceive('disk')->with('local')->andReturn($sourceDisk);
        Storage::shouldReceive('disk')->with('r2')->andReturn($publicDisk);

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertSessionHas('error', 'No se pudo preparar la copia pública de la imagen.');

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_FAILED, $hosting->status);
        $this->assertSame('PUBLIC_MEDIA_STORAGE_FAILED', $hosting->last_error_code);
        $this->assertNull($hosting->public_url);
        $this->assertStringNotContainsString('credential', Str::lower($hosting->toJson()));
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function storageExceptionStages(): array
    {
        return [
            'upload exception' => ['upload'],
            'URL generation exception' => ['url'],
        ];
    }

    public function test_public_disk_must_be_distinct_from_private_source_disk(): void
    {
        Config::set('filesystems.public_media_disk', 'local');
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertSessionHas('error', 'El almacenamiento público debe ser distinto del almacenamiento privado.');

        $this->assertDatabaseHas('public_media_hostings', [
            'publication_media_id' => $media->getKey(),
            'status' => PublicMediaHosting::STATUS_FAILED,
            'last_error_code' => 'PUBLIC_MEDIA_DISK_NOT_DISTINCT',
        ]);
        Http::assertNothingSent();
    }

    #[DataProvider('unapprovedStatuses')]
    public function test_unapproved_or_rejected_media_is_blocked(string $status): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia(['status' => $status]);
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertSessionHas('error', 'La imagen requiere aprobación antes de alojarse.');

        $this->assertSame(0, PublicMediaHosting::query()->count());
        $this->assertSame([], Storage::disk('public-media')->allFiles());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function unapprovedStatuses(): array
    {
        return [
            'uploaded' => [PublicationMedia::STATUS_UPLOADED],
            'rejected' => [PublicationMedia::STATUS_REJECTED],
        ];
    }

    public function test_superseded_media_and_cross_tenant_requests_are_hidden(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia(['superseded_at' => now()]);
        $outsider = User::factory()->create();

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertNotFound();
        $this->actingAs($outsider)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertNotFound();
        $this->actingAs($outsider)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertNotFound();

        $this->assertSame(0, PublicMediaHosting::query()->count());
        Http::assertNothingSent();
    }

    public function test_unconfigured_public_disk_fails_safely(): void
    {
        Config::set('filesystems.public_media_disk', null);
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertSessionHas('error', 'El almacenamiento público no está configurado.');

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_FAILED, $hosting->status);
        $this->assertSame('PUBLIC_MEDIA_DISK_NOT_CONFIGURED', $hosting->last_error_code);
        $this->assertNull($hosting->public_url);
        Http::assertNothingSent();
    }

    public function test_missing_private_file_fails_without_public_object(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia();

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertSessionHas('error', 'No se encontró la imagen privada aprobada.');

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_FAILED, $hosting->status);
        $this->assertSame('PRIVATE_MEDIA_MISSING', $hosting->last_error_code);
        $this->assertSame([], Storage::disk('public-media')->allFiles());
        Http::assertNothingSent();
    }

    public function test_existing_hosted_asset_is_idempotent(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        Storage::disk('local')->put($media->storage_path, "\xFF\xD8\xFFjpeg");

        $route = route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]);
        $this->actingAs($user)->post($route)->assertRedirect();
        $first = PublicMediaHosting::query()->sole();
        $firstObjectKey = $first->object_key;
        $firstHostedAt = $first->hosted_at;
        $this->actingAs($user)->post($route)->assertRedirect();

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame($firstObjectKey, $hosting->object_key);
        $this->assertTrue($firstHostedAt->equalTo($hosting->hosted_at));
        $this->assertCount(1, Storage::disk('public-media')->allFiles());
        Http::assertNothingSent();
    }

    public function test_replacement_starts_without_hosting_and_preserves_old_public_copy(): void
    {
        [$user, $brand, $draft, $oldMedia] = $this->approvedMedia();
        Storage::disk('local')->put($oldMedia->storage_path, "\xFF\xD8\xFFold");
        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $oldMedia]))
            ->assertRedirect();
        $oldHosting = PublicMediaHosting::query()->sole();

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.store', [$brand, $draft]), [
                'image' => UploadedFile::fake()->image('replacement.jpg', 1080, 1080),
            ])
            ->assertRedirect();

        $oldMedia->refresh();
        $newMedia = PublicationMedia::query()->whereNull('superseded_at')->sole();
        $this->assertNotNull($oldMedia->superseded_at);
        $this->assertNull($newMedia->publicHosting);
        $this->assertSame(PublicationMedia::STATUS_UPLOADED, $newMedia->status);
        $this->assertSame(PublicationMedia::PREFLIGHT_NOT_CHECKED, $newMedia->preflight_status);
        $this->assertDatabaseHas('public_media_hostings', [
            'id' => $oldHosting->getKey(),
            'publication_media_id' => $oldMedia->getKey(),
            'status' => PublicMediaHosting::STATUS_HOSTED,
        ]);
        Storage::disk('public-media')->assertExists($oldHosting->object_key);
        Http::assertNothingSent();
    }

    public function test_storage_failure_is_persisted_without_credentials_or_raw_details(): void
    {
        $this->app->bind(PublicMediaStorageInterface::class, fn () => new class implements PublicMediaStorageInterface
        {
            public function store(PublicationMedia $media): PublicMediaStorageResult
            {
                return PublicMediaStorageResult::failed(
                    'PUBLIC_MEDIA_WRITE_FAILED',
                    'No se pudo guardar la copia pública de la imagen.',
                );
            }
        });
        [$user, $brand, $draft, $media] = $this->approvedMedia();

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.hosting.store', [$brand, $draft, $media]))
            ->assertSessionHas('error', 'No se pudo guardar la copia pública de la imagen.');

        $hosting = PublicMediaHosting::query()->sole();
        $this->assertSame(PublicMediaHosting::STATUS_FAILED, $hosting->status);
        $this->assertSame('PUBLIC_MEDIA_WRITE_FAILED', $hosting->last_error_code);
        $this->assertNull($hosting->provider);
        $this->assertNull($hosting->disk);
        $this->assertNull($hosting->object_key);
        $this->assertStringNotContainsString('secret', $hosting->toJson());
        $this->assertStringNotContainsString('credential', $hosting->toJson());
        $this->assertEmpty(array_intersect([
            'credentials',
            'access_key',
            'secret_key',
            'authorization',
        ], Schema::getColumnListing('public_media_hostings')));
        Http::assertNothingSent();
    }

    public function test_managed_url_precedes_manual_url_and_manual_fallback_remains_deterministic(): void
    {
        [, , , $media] = $this->approvedMedia();
        $this->assertSame('https://manual.example.com/image.jpg', $media->effectivePublicUrl());

        $hosting = $this->hostedRecord($media, self::MANAGED_BASE_URL.'/managed.jpg');
        $media->setRelation('publicHosting', $hosting);
        $this->assertSame(self::MANAGED_BASE_URL.'/managed.jpg', $media->effectivePublicUrl());

        $hosting->update(['status' => PublicMediaHosting::STATUS_FAILED]);
        $media->unsetRelation('publicHosting');
        $this->assertSame('https://manual.example.com/image.jpg', $media->effectivePublicUrl());
    }

    public function test_manual_fallback_change_does_not_override_managed_url_or_reset_approval(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia([
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => self::MANAGED_BASE_URL.'/managed.jpg',
        ]);
        $this->hostedRecord($media, self::MANAGED_BASE_URL.'/managed.jpg');

        $this->actingAs($user)
            ->patch(route('marcas.borradores.media.public-url.update', [$brand, $draft, $media]), [
                'public_url' => 'https://fallback.example.org/new.jpg',
            ])
            ->assertRedirect();

        $media->refresh();
        $this->assertSame(PublicationMedia::STATUS_APPROVED, $media->status);
        $this->assertSame(PublicationMedia::PREFLIGHT_PASSED, $media->preflight_status);
        $this->assertSame(self::MANAGED_BASE_URL.'/managed.jpg', $media->effectivePublicUrl());
        Http::assertNothingSent();
    }

    public function test_managed_asset_runs_through_existing_preflight(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        $managedUrl = self::MANAGED_BASE_URL.'/managed.jpg';
        $this->hostedRecord($media, $managedUrl);
        Config::set('services.publication_media.allowed_hosts', ['media.example.com']);
        $this->app->instance(PublicHostResolverInterface::class, new class implements PublicHostResolverInterface
        {
            public function resolve(string $host): array
            {
                return ['8.8.8.8'];
            }
        });
        Http::fake([
            $managedUrl => Http::response("\xFF\xD8\xFFjpeg", 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '7',
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.preflight.store', [$brand, $draft, $media]))
            ->assertRedirect();

        $this->assertSame(PublicationMedia::PREFLIGHT_PASSED, $media->fresh()->preflight_status);
        Http::assertSent(fn (Request $request): bool => $request->url() === $managedUrl);
        Http::assertSentCount(1);
    }

    public function test_ui_distinguishes_private_media_hosting_and_manual_fallback_without_credentials(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia();
        $this->hostedRecord($media, self::MANAGED_BASE_URL.'/managed.jpg');

        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Imagen privada')
            ->assertSee('Public hosting')
            ->assertSee('ALOJADA')
            ->assertSee('HOSTING ADMINISTRADO')
            ->assertSee('URL pública manual / avanzada')
            ->assertDontSee('AWS_ACCESS_KEY_ID')
            ->assertDontSee('AWS_SECRET_ACCESS_KEY');
    }

    public function test_ui_distinguishes_unconfigured_and_not_hosted_states(): void
    {
        [$user, $brand, $draft] = $this->approvedMedia();

        Config::set('filesystems.public_media_disk', null);
        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('NO CONFIGURADO')
            ->assertDontSee('Preparar imagen para publicación');

        Config::set('filesystems.public_media_disk', 'public-media');
        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('NO ALOJADA')
            ->assertSee('Preparar imagen para publicación');
    }

    /** @return array{User, Brand, Draft, PublicationMedia} */
    private function approvedMedia(array $attributes = []): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => 'Borrador aprobado',
            'content' => 'Contenido aprobado',
            'status' => Draft::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);
        $media = PublicationMedia::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'uploaded_by' => $user->getKey(),
            'type' => PublicationMedia::TYPE_IMAGE,
            'original_filename' => 'original.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'publication-media/'.$brand->getKey().'/'.str()->ulid().'.jpg',
            'public_url' => 'https://manual.example.com/image.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 18,
            'width' => 1080,
            'height' => 1080,
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
            ...$attributes,
        ]);

        return [$user, $brand, $draft, $media];
    }

    private function hostedRecord(PublicationMedia $media, string $url): PublicMediaHosting
    {
        return PublicMediaHosting::create([
            'publication_media_id' => $media->getKey(),
            'status' => PublicMediaHosting::STATUS_HOSTED,
            'provider' => 'local',
            'disk' => 'public-media',
            'object_key' => 'publication-media/'.$media->getKey().'/managed.jpg',
            'public_url' => $url,
            'content_type' => 'image/jpeg',
            'size_bytes' => 18,
            'checksum_sha256' => hash('sha256', 'managed'),
            'hosted_at' => now(),
        ]);
    }

    private function configureFakeR2(
        string $publicBaseUrl = 'https://pub-test.r2.dev',
        string $apiEndpoint = 'https://account-id.r2.cloudflarestorage.com',
    ): void {
        Config::set('filesystems.public_media_disk', 'r2');
        Config::set('filesystems.disks.r2', [
            'driver' => 's3',
            'key' => 'test-access-key',
            'secret' => 'test-secret-key',
            'region' => 'auto',
            'bucket' => 'test-public-media',
            'url' => $publicBaseUrl,
            'endpoint' => $apiEndpoint,
            'use_path_style_endpoint' => false,
            'throw' => false,
            'report' => false,
        ]);
        Storage::fake('r2', [
            'url' => $publicBaseUrl,
            'visibility' => 'private',
        ]);
    }
}
