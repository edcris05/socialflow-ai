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
use PHPUnit\Framework\Attributes\DataProvider;
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
}
