<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Draft;
use App\Models\PublicationMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicationMediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
    }

    public function test_owner_can_upload_jpeg_with_metadata_but_it_is_not_approved(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();

        $response = $this->actingAs($user)->post(
            route('marcas.borradores.media.store', [$brand, $draft]),
            [
                'image' => UploadedFile::fake()->image('../../campaign.jpg', 1200, 630)->size(512),
                'public_url' => 'https://cdn.example.com/campaign.jpg',
            ],
        );

        $response->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));
        $media = PublicationMedia::query()->sole();
        $this->assertSame(PublicationMedia::STATUS_UPLOADED, $media->status);
        $this->assertSame('campaign.jpg', $media->original_filename);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame(1200, $media->width);
        $this->assertSame(630, $media->height);
        $this->assertSame('https://cdn.example.com/campaign.jpg', $media->public_url);
        $this->assertStringNotContainsString('..', $media->storage_path);
        $this->assertStringNotContainsString('campaign.jpg', $media->storage_path);
        Storage::disk('local')->assertExists($media->storage_path);
        Http::assertNothingSent();
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_or_oversized_upload_is_rejected(UploadedFile $file): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();

        $response = $this->actingAs($user)
            ->from(route('marcas.borradores.edit', [$brand, $draft]))
            ->post(route('marcas.borradores.media.store', [$brand, $draft]), ['image' => $file]);

        $response->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));
        $response->assertSessionHasErrors('image');
        $this->assertSame(0, PublicationMedia::query()->count());
        Http::assertNothingSent();
    }

    /** @return array<string, array{UploadedFile}> */
    public static function invalidUploads(): array
    {
        return [
            'PHP payload' => [UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')],
            'PNG image' => [UploadedFile::fake()->image('graphic.png', 100, 100)],
            'oversized JPEG' => [UploadedFile::fake()->image('large.jpg', 100, 100)->size(8193)],
        ];
    }

    public function test_draft_must_be_approved_before_upload(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();
        $draft->update(['status' => Draft::STATUS_DRAFT]);

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.store', [$brand, $draft]), [
                'image' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertNotFound();

        $this->assertSame(0, PublicationMedia::query()->count());
    }

    public function test_cross_tenant_upload_view_and_approval_are_hidden(): void
    {
        [$owner, $brand, $draft] = $this->approvedDraft();
        $media = $this->media($owner, $brand, $draft);
        Storage::disk('local')->put($media->storage_path, 'jpeg');
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('marcas.borradores.media.store', [$brand, $draft]), [
                'image' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertNotFound();
        $this->actingAs($outsider)
            ->get(route('marcas.borradores.media.show', [$brand, $draft, $media]))
            ->assertNotFound();
        $this->actingAs($outsider)
            ->patch(route('marcas.borradores.media.approve', [$brand, $draft, $media]))
            ->assertNotFound();

        $this->assertSame(PublicationMedia::STATUS_UPLOADED, $media->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_owner_can_preview_private_image_with_safe_headers(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();
        $media = $this->media($user, $brand, $draft);
        Storage::disk('local')->put($media->storage_path, 'jpeg-content');

        $response = $this->actingAs($user)
            ->get(route('marcas.borradores.media.show', [$brand, $draft, $media]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_approval_and_rejection_are_explicit_media_decisions(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();
        $media = $this->media($user, $brand, $draft);

        $this->actingAs($user)
            ->patch(route('marcas.borradores.media.approve', [$brand, $draft, $media]))
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $media->refresh();
        $this->assertSame(PublicationMedia::STATUS_APPROVED, $media->status);
        $this->assertSame($user->getKey(), $media->approved_by);
        $this->assertNotNull($media->approved_at);

        $this->actingAs($user)
            ->patch(route('marcas.borradores.media.reject', [$brand, $draft, $media]), [
                'rejection_reason' => 'No corresponde a la campaña.',
            ])
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $media->refresh();
        $this->assertSame(PublicationMedia::STATUS_REJECTED, $media->status);
        $this->assertNull($media->approved_at);
        $this->assertSame($user->getKey(), $media->rejected_by);
        $this->assertSame('No corresponde a la campaña.', $media->rejection_reason);
    }

    public function test_replacement_preserves_old_file_and_resets_approval(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();
        $oldMedia = $this->media($user, $brand, $draft, [
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);
        Storage::disk('local')->put($oldMedia->storage_path, 'old-jpeg');

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.store', [$brand, $draft]), [
                'image' => UploadedFile::fake()->image('replacement.jpg', 1080, 1080),
                'public_url' => 'https://cdn.example.com/replacement.jpg',
            ])
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $oldMedia->refresh();
        $newMedia = PublicationMedia::query()->whereNull('superseded_at')->sole();
        $this->assertNotNull($oldMedia->superseded_at);
        $this->assertSame(PublicationMedia::STATUS_APPROVED, $oldMedia->status);
        Storage::disk('local')->assertExists($oldMedia->storage_path);
        $this->assertSame(PublicationMedia::STATUS_UPLOADED, $newMedia->status);
        $this->assertNull($newMedia->approved_by);
        $this->assertNull($newMedia->approved_at);
        $this->assertSame(PublicationMedia::PREFLIGHT_NOT_CHECKED, $newMedia->preflight_status);
        $this->assertNull($newMedia->preflight_checked_at);
    }

    public function test_changing_public_url_resets_existing_approval(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();
        $media = $this->media($user, $brand, $draft, [
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => 'https://cdn.example.com/image.jpg',
            'preflight_content_type' => 'image/jpeg',
            'preflight_content_length' => 1024,
        ]);

        $this->actingAs($user)
            ->patch(route('marcas.borradores.media.public-url.update', [$brand, $draft, $media]), [
                'public_url' => 'https://images.example.org/new.jpg',
            ])
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $media->refresh();
        $this->assertSame('https://images.example.org/new.jpg', $media->public_url);
        $this->assertSame(PublicationMedia::STATUS_UPLOADED, $media->status);
        $this->assertNull($media->approved_by);
        $this->assertNull($media->approved_at);
        $this->assertSame(PublicationMedia::PREFLIGHT_NOT_CHECKED, $media->preflight_status);
        $this->assertNull($media->preflight_checked_at);
        $this->assertNull($media->preflight_final_url);
        $this->assertNull($media->preflight_content_type);
        $this->assertNull($media->preflight_content_length);
    }

    #[DataProvider('blockedPublicUrls')]
    public function test_local_or_private_public_url_is_rejected(string $url): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();
        $media = $this->media($user, $brand, $draft);

        $response = $this->actingAs($user)
            ->from(route('marcas.borradores.edit', [$brand, $draft]))
            ->patch(route('marcas.borradores.media.public-url.update', [$brand, $draft, $media]), [
                'public_url' => $url,
            ]);

        $response->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));
        $response->assertSessionHasErrors('public_url');
        $this->assertSame('https://cdn.example.com/image.jpg', $media->fresh()->public_url);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function blockedPublicUrls(): array
    {
        return [
            'localhost' => ['http://localhost/image.jpg'],
            'loopback IPv4' => ['http://127.0.0.1/image.jpg'],
            'loopback IPv6' => ['http://[::1]/image.jpg'],
            'DDEV host' => ['https://socialflow-ai.ddev.site/image.jpg'],
            'private IPv4' => ['http://192.168.1.2/image.jpg'],
            'internal hostname' => ['https://assets.internal/image.jpg'],
        ];
    }

    public function test_approved_draft_ui_shows_media_status_and_controls(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();

        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('SIN IMAGEN')
            ->assertSee('Cargar imagen');

        $this->media($user, $brand, $draft);

        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('CARGADA — SIN APROBAR')
            ->assertSee('Aprobar imagen')
            ->assertSee('URL pública manual / avanzada')
            ->assertSee('NO CONFIGURADO');

        $media = PublicationMedia::query()->sole();
        $media->update([
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Estado preflight:')
            ->assertSee('SIN VERIFICAR')
            ->assertSee('Verificar imagen pública');
    }

    /** @return array{User, Brand, Draft} */
    private function approvedDraft(): array
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

        return [$user, $brand, $draft];
    }

    /** @param array<string, mixed> $attributes */
    private function media(User $user, Brand $brand, Draft $draft, array $attributes = []): PublicationMedia
    {
        return PublicationMedia::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'uploaded_by' => $user->getKey(),
            'type' => PublicationMedia::TYPE_IMAGE,
            'original_filename' => 'image.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'publication-media/'.$brand->getKey().'/'.str()->ulid().'.jpg',
            'public_url' => 'https://cdn.example.com/image.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1080,
            'height' => 1080,
            'status' => PublicationMedia::STATUS_UPLOADED,
            ...$attributes,
        ]);
    }
}
