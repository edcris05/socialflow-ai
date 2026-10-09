<?php

namespace Tests\Feature;

use App\Contracts\PublicHostResolverInterface;
use App\Models\Brand;
use App\Models\BrandAutopublishingSetting;
use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\ScheduledPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualPublicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MEDIA_URL = 'https://media.example.com/approved.jpg';

    private const CREATE_URL = 'https://graph.instagram.com/v26.0/ig_123/media';

    private const PUBLISH_URL = 'https://graph.instagram.com/v26.0/ig_123/media_publish';

    private const STATUS_URL = 'https://graph.instagram.com/v26.0/container_123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-04 12:00:00');
        Config::set('services.publication_media.allowed_hosts', ['media.example.com']);
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        $this->app->instance(PublicHostResolverInterface::class, new class implements PublicHostResolverInterface
        {
            public function resolve(string $host): array
            {
                return ['8.8.8.8'];
            }
        });
    }

    public function test_gate_off_blocks_media_preflight_and_meta_http(): void
    {
        Config::set('services.meta.publishing_enabled', false);
        [$user, $brand, , $publication] = $this->publication();

        $this->publish($user, $brand, $publication)->assertSessionHas('error');

        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_explicit_confirmation_is_required_server_side_before_any_http(): void
    {
        [$user, $brand, , $publication] = $this->publication();

        $this->actingAs($user)
            ->post(route('marcas.programacion.publicar.store', [$brand, $publication]))
            ->assertSessionHasErrors('confirm_publish');

        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    #[DataProvider('manipulatedConfirmations')]
    public function test_manipulated_confirmation_is_rejected_with_422_before_any_http(mixed $confirmation): void
    {
        [$user, $brand, , $publication] = $this->publication();

        $this->actingAs($user)
            ->postJson(
                route('marcas.programacion.publicar.store', [$brand, $publication]),
                ['confirm_publish' => $confirmation],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm_publish');

        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    /** @return array<string, array{mixed}> */
    public static function manipulatedConfirmations(): array
    {
        return [
            'boolean false' => [false],
            'yes string' => ['yes'],
            'one string' => ['1'],
        ];
    }

    #[DataProvider('blockedLocalPreconditions')]
    public function test_local_preconditions_block_before_media_or_meta_http(string $scenario): void
    {
        [$user, $brand, $draft, $publication, $connection, $media] = $this->publication();

        match ($scenario) {
            'future' => $publication->update(['scheduled_for' => now()->addMinute()]),
            'cancelled' => $publication->update(['status' => ScheduledPublication::STATUS_CANCELLED]),
            'draft' => $draft->update(['status' => Draft::STATUS_DRAFT]),
            'media' => $media->update(['status' => PublicationMedia::STATUS_UPLOADED]),
            'rejected-media' => $media->update(['status' => PublicationMedia::STATUS_REJECTED]),
            'connection' => $connection->update(['status' => MetaConnection::STATUS_CONFIGURED_UNVERIFIED]),
            'token' => $connection->update(['access_token' => null]),
            'account' => $connection->update(['instagram_account_id' => null]),
        };

        $this->publish($user, $brand, $publication)->assertSessionHas('error');

        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function blockedLocalPreconditions(): array
    {
        return [
            'future schedule' => ['future'],
            'cancelled schedule' => ['cancelled'],
            'unapproved draft' => ['draft'],
            'unapproved media' => ['media'],
            'rejected media' => ['rejected-media'],
            'unverified connection' => ['connection'],
            'missing token' => ['token'],
            'missing account' => ['account'],
        ];
    }

    public function test_failed_immediate_preflight_blocks_meta_publishing_http_and_sanitizes_body(): void
    {
        Http::fake([
            self::MEDIA_URL => Http::response('raw-secret-body', 200, ['Content-Type' => 'text/html']),
        ]);
        [$user, $brand, , $publication, , $media] = $this->publication();

        $this->publish($user, $brand, $publication)->assertSessionHas('error');

        $this->assertSame(PublicationMedia::PREFLIGHT_FAILED, $media->fresh()->preflight_status);
        $this->assertStringNotContainsString('raw-secret-body', $media->fresh()->toJson());
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.instagram.com/'));
    }

    public function test_success_runs_preflight_then_container_then_publish_and_persists_published_attempt(): void
    {
        $order = [];
        Http::fake(function (Request $request) use (&$order) {
            if ($request->url() === self::MEDIA_URL) {
                $order[] = 'preflight';

                return Http::response("\xFF\xD8\xFFjpeg", 200, [
                    'Content-Type' => 'image/jpeg',
                    'Content-Length' => '7',
                ]);
            }

            if ($request->url() === self::CREATE_URL) {
                $order[] = 'create';

                return Http::response(['id' => 'container_123']);
            }

            if (str_starts_with($request->url(), self::STATUS_URL)) {
                $order[] = 'status';

                return Http::response(['status_code' => 'FINISHED']);
            }

            $order[] = 'publish';

            return Http::response(['id' => 'media_123']);
        });
        [$user, $brand, , $publication, , $media] = $this->publication();

        $this->publish($user, $brand, $publication)->assertSessionHas('status');

        $this->assertSame(['preflight', 'create', 'status', 'publish'], $order);
        $this->assertTrue($media->fresh()->hasFreshPreflight());
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_PUBLISHED, $attempt->status);
        $this->assertSame('container_123', $attempt->external_container_id);
        $this->assertSame('media_123', $attempt->external_media_id);
        $this->assertSame(1, $attempt->attempt_count);
    }

    public function test_manual_publish_remains_available_when_brand_autopublishing_is_disabled(): void
    {
        Http::fake([
            self::MEDIA_URL => Http::response("\xFF\xD8\xFFjpeg", 200, ['Content-Type' => 'image/jpeg']),
            self::CREATE_URL => Http::response(['id' => 'container_123']),
            self::STATUS_URL.'*' => Http::response(['status_code' => 'FINISHED']),
            self::PUBLISH_URL => Http::response(['id' => 'media_123']),
        ]);
        [$user, $brand, , $publication] = $this->publication();
        BrandAutopublishingSetting::factory()->for($brand)->create([
            'disabled_by' => $user->getKey(),
            'disabled_at' => now(),
        ]);

        $this->publish($user, $brand, $publication)->assertSessionHas('status');

        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_PUBLISHED, $attempt->status);
        $this->assertSame('media_123', $attempt->external_media_id);
        Http::assertSentCount(4);
    }

    public function test_second_click_after_published_is_idempotent_with_zero_extra_http(): void
    {
        Http::fake([
            self::MEDIA_URL => Http::response("\xFF\xD8\xFFjpeg", 200, ['Content-Type' => 'image/jpeg']),
            self::CREATE_URL => Http::response(['id' => 'container_123']),
            self::STATUS_URL.'*' => Http::response(['status_code' => 'FINISHED']),
            self::PUBLISH_URL => Http::response(['id' => 'media_123']),
        ]);
        [$user, $brand, , $publication] = $this->publication();

        $this->publish($user, $brand, $publication)->assertSessionHas('status');
        $this->publish($user, $brand, $publication)->assertSessionHas('status');

        $this->assertSame(1, PublicationAttempt::query()->count());
        Http::assertSentCount(4);
    }

    #[DataProvider('blockedAttemptStatuses')]
    public function test_existing_attempt_status_blocks_reentry_before_preflight_or_meta(string $status): void
    {
        [$user, $brand, , $publication, $connection] = $this->publication();
        $this->attempt($publication, $connection, $status);

        $this->publish($user, $brand, $publication);

        $this->assertSame(1, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function blockedAttemptStatuses(): array
    {
        return [
            'publishing' => [PublicationAttempt::STATUS_PUBLISHING],
            'published' => [PublicationAttempt::STATUS_PUBLISHED],
            'outcome unknown' => [PublicationAttempt::STATUS_OUTCOME_UNKNOWN],
            'failed' => [PublicationAttempt::STATUS_FAILED],
        ];
    }

    public function test_cross_tenant_manual_publish_is_hidden_before_any_http(): void
    {
        [, $brand, , $publication] = $this->publication();
        $outsider = User::factory()->create();

        $this->publish($outsider, $brand, $publication)->assertNotFound();

        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_superseded_media_cannot_be_selected_for_manual_publication(): void
    {
        [$user, $brand, $draft, $publication, , $supersededMedia] = $this->publication();
        $supersededMedia->update(['superseded_at' => now()]);
        $replacementMedia = PublicationMedia::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'uploaded_by' => $user->getKey(),
            'type' => PublicationMedia::TYPE_IMAGE,
            'original_filename' => 'replacement.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'publication-media/'.$brand->getKey().'/replacement.jpg',
            'public_url' => 'https://media.example.com/replacement.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'status' => PublicationMedia::STATUS_UPLOADED,
        ]);

        $this->actingAs($user)
            ->get(route('marcas.programacion.index', $brand))
            ->assertOk()
            ->assertSee('replacement.jpg')
            ->assertSee(route('marcas.borradores.media.show', [$brand, $draft, $replacementMedia]), false)
            ->assertDontSee('approved.jpg');

        $this->publish($user, $brand, $publication)->assertSessionHas('error');

        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_expired_preflight_is_shown_as_requiring_a_new_check(): void
    {
        [$user, $brand, , , , $media] = $this->publication();
        $media->update(['preflight_checked_at' => now()->subHour()]);

        $this->actingAs($user)
            ->get(route('marcas.programacion.index', $brand))
            ->assertOk()
            ->assertSee('REQUIERE NUEVA COMPROBACIÓN (VENCIDA)')
            ->assertSee('El preflight debe comprobarse nuevamente.')
            ->assertDontSee('name="confirm_publish"', false);

        Http::assertNothingSent();
    }

    public function test_final_review_shows_exact_publish_context_without_exposing_token(): void
    {
        [$user, $brand, $draft, , , $media] = $this->publication();
        $draft->update(['content' => "Caption completo\nSegunda línea <script>alert('xss')</script>"]);

        $this->actingAs($user)
            ->get(route('marcas.programacion.index', $brand))
            ->assertOk()
            ->assertSee('Revisión antes de publicar')
            ->assertSee("Caption completo\nSegunda línea <script>alert('xss')</script>")
            ->assertDontSee("<script>alert('xss')</script>", false)
            ->assertSee(route('marcas.borradores.media.show', [$brand, $draft, $media]), false)
            ->assertSee('approved.jpg')
            ->assertSee('media.example.com')
            ->assertSee('ig_123')
            ->assertSee('Meta: VERIFICADO')
            ->assertSeeInOrder(['Preflight:', 'VERIFICADA'])
            ->assertSee('Estado: READY')
            ->assertSee('SIN INTENTOS')
            ->assertSee('Publicar ahora en Instagram')
            ->assertSee('Confirmo que revisé el texto, la imagen y la cuenta de destino, y quiero publicar este contenido realmente en Instagram.')
            ->assertDontSee('fake-meta-token');

        Http::assertNothingSent();
    }

    public function test_cross_tenant_user_cannot_open_final_review_page(): void
    {
        [, $brand] = $this->publication();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('marcas.programacion.index', $brand))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_gate_off_is_clear_in_final_review_and_backend_remains_blocked(): void
    {
        Config::set('services.meta.publishing_enabled', false);
        [$user, $brand, , $publication] = $this->publication();

        $this->actingAs($user)
            ->get(route('marcas.programacion.index', $brand))
            ->assertOk()
            ->assertSee('La publicación real está deshabilitada por configuración.')
            ->assertDontSee('name="confirm_publish"', false);

        $this->publish($user, $brand, $publication)->assertSessionHas('error');

        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    #[DataProvider('nonRepeatableAttemptStatuses')]
    public function test_terminal_or_uncertain_attempt_does_not_offer_functional_republish(string $status): void
    {
        [$user, $brand, , $publication, $connection] = $this->publication();
        $this->attempt($publication, $connection, $status);

        $this->actingAs($user)
            ->get(route('marcas.programacion.index', $brand))
            ->assertOk()
            ->assertSee($status === PublicationAttempt::STATUS_PUBLISHED ? 'PUBLISHED' : 'OUTCOME UNKNOWN')
            ->assertDontSee('name="confirm_publish"', false)
            ->assertDontSee('action="'.route('marcas.programacion.publicar.store', [$brand, $publication]).'"', false);

        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function nonRepeatableAttemptStatuses(): array
    {
        return [
            'published' => [PublicationAttempt::STATUS_PUBLISHED],
            'outcome unknown' => [PublicationAttempt::STATUS_OUTCOME_UNKNOWN],
        ];
    }

    private function publish(User $user, Brand $brand, ScheduledPublication $publication)
    {
        return $this->actingAs($user)->post(
            route('marcas.programacion.publicar.store', [$brand, $publication]),
            ['confirm_publish' => 'true'],
        );
    }

    /** @return array{User, Brand, Draft, ScheduledPublication, MetaConnection, PublicationMedia} */
    private function publication(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => 'Publicación aprobada',
            'content' => 'Contenido aprobado',
            'status' => Draft::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now()->subHour(),
        ]);
        $publication = ScheduledPublication::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'scheduled_by' => $user->getKey(),
            'scheduled_for' => now()->subMinute(),
            'status' => ScheduledPublication::STATUS_SCHEDULED,
        ]);
        $connection = MetaConnection::create([
            'brand_id' => $brand->getKey(),
            'configured_by' => $user->getKey(),
            'instagram_account_id' => 'ig_123',
            'access_token' => 'fake-meta-token',
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => now(),
        ]);
        $media = PublicationMedia::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'uploaded_by' => $user->getKey(),
            'type' => PublicationMedia::TYPE_IMAGE,
            'original_filename' => 'approved.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'publication-media/'.$brand->getKey().'/approved.jpg',
            'public_url' => self::MEDIA_URL,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now()->subHour(),
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => self::MEDIA_URL,
            'preflight_content_type' => 'image/jpeg',
            'preflight_content_length' => 1024,
        ]);

        return [$user, $brand, $draft, $publication, $connection, $media];
    }

    private function attempt(
        ScheduledPublication $publication,
        MetaConnection $connection,
        string $status,
    ): PublicationAttempt {
        return PublicationAttempt::create([
            'scheduled_publication_id' => $publication->getKey(),
            'meta_connection_id' => $connection->getKey(),
            'initiated_by' => $publication->scheduled_by,
            'provider' => 'meta',
            'status' => $status,
            'attempt_count' => 1,
            'idempotency_key' => hash('sha256', 'meta|'.$publication->getKey().'|ig_123'),
            'target_account_id_snapshot' => 'ig_123',
            'caption_snapshot' => 'Contenido aprobado',
            'media_url_snapshot' => self::MEDIA_URL,
            'external_container_id' => in_array($status, [PublicationAttempt::STATUS_PUBLISHED, PublicationAttempt::STATUS_OUTCOME_UNKNOWN], true)
                ? 'container_existing'
                : null,
            'external_media_id' => $status === PublicationAttempt::STATUS_PUBLISHED ? 'media_existing' : null,
            'last_error_code' => $status === PublicationAttempt::STATUS_OUTCOME_UNKNOWN ? 'META_PUBLISH_OUTCOME_UNKNOWN' : null,
            'last_error_message' => $status === PublicationAttempt::STATUS_OUTCOME_UNKNOWN ? 'Resultado incierto.' : null,
            'started_at' => now(),
        ]);
    }
}
