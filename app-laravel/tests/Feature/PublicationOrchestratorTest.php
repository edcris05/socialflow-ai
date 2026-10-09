<?php

namespace Tests\Feature;

use App\Contracts\MetaPublisherInterface;
use App\Models\Brand;
use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\PublicMediaHosting;
use App\Models\ScheduledPublication;
use App\Models\User;
use App\Services\Meta\MetaContainerStatus;
use App\Services\Meta\MetaContainerStatusResult;
use App\Services\Publishing\PublicationOrchestrator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeMetaPublisher;
use Tests\TestCase;

class PublicationOrchestratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CREATE_URL = 'https://graph.instagram.com/v26.0/ig_123/media';

    private const PUBLISH_URL = 'https://graph.instagram.com/v26.0/ig_123/media_publish';

    private const STATUS_URL = 'https://graph.instagram.com/v26.0/container_123';

    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    public function test_successful_publication_persists_lifecycle_ids_and_immutable_snapshots(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->url() === self::CREATE_URL) {
                $this->assertSame('Contenido aprobado', $request['caption']);
                $this->assertSame('https://cdn.example.com/approved.jpg', $request['image_url']);

                return Http::response(['id' => 'container_123']);
            }

            if (str_starts_with($request->url(), self::STATUS_URL)) {
                $attempt = PublicationAttempt::query()->sole();
                $this->assertSame('container_123', $attempt->external_container_id);
                $this->assertSame(PublicationAttempt::STATUS_PUBLISHING, $attempt->status);

                return Http::response(['status_code' => 'FINISHED', 'status' => 'FINISHED']);
            }

            return Http::response(['id' => 'media_123']);
        });
        [$user, $brand, $draft, $publication, $connection] = $this->publication();
        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertTrue($result->successful);
        $this->assertSame('container_123', $result->externalContainerId);
        $this->assertSame('media_123', $result->externalMediaId);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame($publication->getKey(), $attempt->scheduled_publication_id);
        $this->assertSame($connection->getKey(), $attempt->meta_connection_id);
        $this->assertSame($user->getKey(), $attempt->initiated_by);
        $this->assertSame('meta', $attempt->provider);
        $this->assertSame(PublicationAttempt::STATUS_PUBLISHED, $attempt->status);
        $this->assertSame(1, $attempt->attempt_count);
        $this->assertSame('ig_123', $attempt->target_account_id_snapshot);
        $this->assertSame('Contenido aprobado', $attempt->caption_snapshot);
        $this->assertSame('https://cdn.example.com/approved.jpg', $attempt->media_url_snapshot);
        $this->assertSame('container_123', $attempt->external_container_id);
        $this->assertSame('media_123', $attempt->external_media_id);
        $this->assertSame('2026-10-04 12:00:00', $attempt->started_at?->toDateTimeString());
        $this->assertSame('2026-10-04 12:00:00', $attempt->completed_at?->toDateTimeString());
        $this->assertSame('2026-10-04 12:00:00', $attempt->published_at?->toDateTimeString());
        $this->assertNull($attempt->last_error_code);
        $this->assertNull($attempt->last_error_message);
        $draft->update(['content' => 'Edición posterior']);
        PublicationMedia::query()->where('draft_id', $draft->getKey())->update([
            'public_url' => 'https://cdn.example.com/replacement.jpg',
        ]);
        $this->assertSame('Contenido aprobado', $attempt->fresh()->caption_snapshot);
        $this->assertSame('https://cdn.example.com/approved.jpg', $attempt->fresh()->media_url_snapshot);
        $this->assertSame($brand->getKey(), $attempt->scheduledPublication->brand_id);
        Http::assertSentCount(3);
    }

    public function test_finished_on_first_check_publishes_once_without_sleeping(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Sleep::fake();
        $fake = new FakeMetaPublisher;
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertTrue($result->successful);
        $this->assertSame(1, $fake->createCalls);
        $this->assertSame(1, $fake->statusCalls);
        $this->assertSame(1, $fake->publishCalls);
        $this->assertSame(['create', 'status', 'publish'], $fake->calls);
        $this->assertSame(PublicationAttempt::STATUS_PUBLISHED, PublicationAttempt::query()->sole()->status);
        Sleep::assertNeverSlept();
        Http::assertNothingSent();
    }

    public function test_finished_on_tenth_check_publishes_after_nine_sleeps(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Sleep::fake();
        $fake = new FakeMetaPublisher;
        $fake->statusResults = array_fill(
            0,
            9,
            MetaContainerStatusResult::succeeded(MetaContainerStatus::IN_PROGRESS),
        );
        $fake->statusResults[] = MetaContainerStatusResult::succeeded(MetaContainerStatus::FINISHED);
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertTrue($result->successful);
        $this->assertSame(10, $fake->statusCalls);
        $this->assertSame(1, $fake->publishCalls);
        $this->assertSame(
            ['create', ...array_fill(0, 10, 'status'), 'publish'],
            $fake->calls,
        );
        Sleep::assertSlept(
            fn ($duration): bool => (int) $duration->totalMilliseconds === 2000,
            9,
        );
        Sleep::assertSleptTimes(9);
        Http::assertNothingSent();
    }

    public function test_in_progress_on_tenth_check_fails_after_nine_sleeps_without_publish(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Sleep::fake();
        $fake = new FakeMetaPublisher;
        $fake->statusResults = [MetaContainerStatusResult::succeeded(MetaContainerStatus::IN_PROGRESS)];
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame('META_CONTAINER_NOT_READY', $result->errorCode);
        $this->assertSame(10, $fake->statusCalls);
        $this->assertSame(0, $fake->publishCalls);
        $this->assertSame(['create', ...array_fill(0, 10, 'status')], $fake->calls);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('fake_container', $attempt->external_container_id);
        Sleep::assertSlept(
            fn ($duration): bool => (int) $duration->totalMilliseconds === 2000,
            9,
        );
        Sleep::assertSleptTimes(9);
        Http::assertNothingSent();
    }

    #[DataProvider('invalidPollingConfigurations')]
    public function test_invalid_polling_configuration_uses_safe_defaults(
        mixed $maxAttempts,
        mixed $pollIntervalMilliseconds,
    ): void {
        Config::set('services.meta.publishing_enabled', true);
        Config::set('services.meta.container_status_max_attempts', $maxAttempts);
        Config::set('services.meta.container_status_poll_interval_ms', $pollIntervalMilliseconds);
        Http::preventStrayRequests();
        Sleep::fake();
        $fake = new FakeMetaPublisher;
        $fake->statusResults = array_fill(
            0,
            9,
            MetaContainerStatusResult::succeeded(MetaContainerStatus::IN_PROGRESS),
        );
        $fake->statusResults[] = MetaContainerStatusResult::succeeded(MetaContainerStatus::FINISHED);
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertTrue($result->successful);
        $this->assertSame(10, $fake->statusCalls);
        $this->assertSame(1, $fake->publishCalls);
        Sleep::assertSlept(
            fn ($duration): bool => (int) $duration->totalMilliseconds === 2000,
            9,
        );
        Sleep::assertSleptTimes(9);
        Http::assertNothingSent();
    }

    /** @return array<string, array{mixed, mixed}> */
    public static function invalidPollingConfigurations(): array
    {
        return [
            'zero' => [0, 0],
            'negative' => [-1, -1],
            'empty' => ['', ''],
            'non-numeric' => ['invalid', 'invalid'],
            'above safe maximum' => [11, 2001],
        ];
    }

    public function test_valid_lower_polling_configuration_is_honored(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Config::set('services.meta.container_status_max_attempts', 3);
        Config::set('services.meta.container_status_poll_interval_ms', 125);
        Http::preventStrayRequests();
        Sleep::fake();
        $fake = new FakeMetaPublisher;
        $fake->statusResults = [
            MetaContainerStatusResult::succeeded(MetaContainerStatus::IN_PROGRESS),
            MetaContainerStatusResult::succeeded(MetaContainerStatus::FINISHED),
        ];
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertTrue($result->successful);
        $this->assertSame(2, $fake->statusCalls);
        $this->assertSame(1, $fake->publishCalls);
        Sleep::assertSequence([Sleep::for(125)->milliseconds()]);
        Http::assertNothingSent();
    }

    #[DataProvider('terminalContainerStatuses')]
    public function test_terminal_container_status_blocks_publish(
        MetaContainerStatus $status,
        string $expectedCode,
        string $expectedAttemptStatus,
        bool $outcomeUncertain,
    ): void {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Sleep::fake();
        $fake = new FakeMetaPublisher;
        $fake->statusResults = [MetaContainerStatusResult::succeeded($status)];
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame($expectedCode, $result->errorCode);
        $this->assertSame($outcomeUncertain, $result->outcomeUncertain);
        $this->assertSame(1, $fake->statusCalls);
        $this->assertSame(0, $fake->publishCalls);
        $this->assertSame(['create', 'status'], $fake->calls);
        $this->assertSame($expectedAttemptStatus, PublicationAttempt::query()->sole()->status);
        Sleep::assertNeverSlept();
        Http::assertNothingSent();
    }

    /** @return array<string, array{MetaContainerStatus, string, string, bool}> */
    public static function terminalContainerStatuses(): array
    {
        return [
            'processing error' => [
                MetaContainerStatus::ERROR,
                'META_CONTAINER_PROCESSING_FAILED',
                PublicationAttempt::STATUS_FAILED,
                false,
            ],
            'expired' => [
                MetaContainerStatus::EXPIRED,
                'META_CONTAINER_EXPIRED',
                PublicationAttempt::STATUS_FAILED,
                false,
            ],
            'unexpectedly published' => [
                MetaContainerStatus::PUBLISHED,
                'META_CONTAINER_ALREADY_PUBLISHED',
                PublicationAttempt::STATUS_OUTCOME_UNKNOWN,
                true,
            ],
        ];
    }

    public function test_invalid_container_status_failure_is_persisted_without_publish(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Sleep::fake();
        $fake = new FakeMetaPublisher;
        $fake->statusResults = [MetaContainerStatusResult::failed(
            'META_CONTAINER_STATUS_INVALID',
            'Instagram devolvió un estado de contenedor inválido.',
        )];
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame('META_CONTAINER_STATUS_INVALID', $result->errorCode);
        $this->assertSame(0, $fake->publishCalls);
        $this->assertSame(['create', 'status'], $fake->calls);
        $this->assertSame(PublicationAttempt::STATUS_FAILED, PublicationAttempt::query()->sole()->status);
        Sleep::assertNeverSlept();
        Http::assertNothingSent();
    }

    #[DataProvider('containerStatusHttpFailures')]
    public function test_container_status_http_failure_preserves_container_and_fails_deterministically(
        int $httpStatus,
        string $expectedCode,
    ): void {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_123']),
            self::STATUS_URL.'*' => Http::response([
                'error' => ['message' => 'raw fake-meta-token'],
            ], $httpStatus),
        ]);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertFalse($result->outcomeUncertain);
        $this->assertSame($expectedCode, $result->errorCode);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('container_123', $attempt->external_container_id);
        $this->assertStringNotContainsString('raw', $attempt->toJson());
        $this->assertStringNotContainsString('fake-meta-token', $attempt->last_error_message ?? '');
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::PUBLISH_URL);
    }

    /** @return array<string, array{int, string}> */
    public static function containerStatusHttpFailures(): array
    {
        return [
            'unauthorized' => [401, 'META_AUTH_FAILED'],
            'forbidden' => [403, 'META_AUTH_FAILED'],
            'server error' => [500, 'META_CONTAINER_STATUS_FAILED'],
        ];
    }

    public function test_container_status_network_failure_preserves_container_and_fails_deterministically(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_123']),
            self::STATUS_URL.'*' => Http::failedConnection('raw status network detail'),
        ]);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertFalse($result->outcomeUncertain);
        $this->assertSame('META_CONTAINER_STATUS_FAILED', $result->errorCode);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('container_123', $attempt->external_container_id);
        $this->assertStringNotContainsString('raw status network detail', $attempt->toJson());
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::PUBLISH_URL);
    }

    public function test_double_execution_returns_published_result_without_new_http(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_123']),
            self::STATUS_URL.'*' => Http::response(['status_code' => 'FINISHED']),
            self::PUBLISH_URL => Http::response(['id' => 'media_123']),
        ]);
        [$user, , , $publication] = $this->publication();

        $first = $this->orchestrator()->publish($user, $publication);
        $second = $this->orchestrator()->publish($user, $publication);

        $this->assertTrue($first->successful);
        $this->assertTrue($second->successful);
        $this->assertFalse($first->alreadyPublished);
        $this->assertTrue($second->alreadyPublished);
        $this->assertSame($first->externalContainerId, $second->externalContainerId);
        $this->assertSame($first->externalMediaId, $second->externalMediaId);
        $this->assertSame(1, PublicationAttempt::query()->count());
        Http::assertSentCount(3);
    }

    public function test_existing_publishing_attempt_blocks_parallel_http(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        [$user, , , $publication, $connection] = $this->publication();
        $this->attempt($publication, $connection, PublicationAttempt::STATUS_PUBLISHING);

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame('PUBLICATION_IN_PROGRESS', $result->errorCode);
        $this->assertSame(1, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_failed_attempt_is_not_retried_automatically(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        [$user, , , $publication, $connection] = $this->publication();
        $this->attempt(
            $publication,
            $connection,
            PublicationAttempt::STATUS_FAILED,
            ['last_error_code' => 'META_CREATE_FAILED', 'last_error_message' => 'Error seguro.'],
        );

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame('META_CREATE_FAILED', $result->errorCode);
        $this->assertSame('Error seguro.', $result->errorMessage);
        $this->assertFalse($result->outcomeUncertain);
        $this->assertSame(PublicationAttempt::STATUS_FAILED, PublicationAttempt::query()->sole()->status);
        Http::assertNothingSent();
    }

    public function test_feature_gate_defaults_off_before_attempt_or_http(): void
    {
        Config::set('services.meta.publishing_enabled', false);
        Http::preventStrayRequests();
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame('PUBLISHING_DISABLED', $result->errorCode);
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_managed_hosting_never_bypasses_required_preflight(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        [$user, , $draft, $publication] = $this->publication();
        $media = PublicationMedia::query()->where('draft_id', $draft->getKey())->sole();
        $media->update([
            'public_url' => null,
            ...PublicationMedia::resetPreflightAttributes(),
        ]);
        PublicMediaHosting::create([
            'publication_media_id' => $media->getKey(),
            'status' => PublicMediaHosting::STATUS_HOSTED,
            'provider' => 'local',
            'disk' => 'public-media',
            'object_key' => 'publication-media/'.$media->getKey().'/managed.jpg',
            'public_url' => 'https://media.example.com/managed.jpg',
            'content_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'managed'),
            'hosted_at' => now(),
        ]);

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame('MEDIA_PREFLIGHT_REQUIRED', $result->errorCode);
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_managed_hosting_url_is_used_for_meta_payload_and_snapshot(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_managed']),
            'https://graph.instagram.com/v26.0/container_managed*' => Http::response(['status_code' => 'FINISHED']),
            self::PUBLISH_URL => Http::response(['id' => 'media_managed']),
        ]);
        [$user, , $draft, $publication] = $this->publication();
        $media = PublicationMedia::query()->where('draft_id', $draft->getKey())->sole();
        $managedUrl = 'https://media.example.com/managed.jpg';
        PublicMediaHosting::create([
            'publication_media_id' => $media->getKey(),
            'status' => PublicMediaHosting::STATUS_HOSTED,
            'provider' => 'local',
            'disk' => 'public-media',
            'object_key' => 'publication-media/'.$media->getKey().'/managed.jpg',
            'public_url' => $managedUrl,
            'content_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'managed'),
            'hosted_at' => now(),
        ]);
        $media->update(['preflight_final_url' => $managedUrl]);

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertTrue($result->successful);
        $this->assertSame($managedUrl, PublicationAttempt::query()->sole()->media_url_snapshot);
        Http::assertSent(fn (Request $request): bool => $request->url() !== self::CREATE_URL
            || $request['image_url'] === $managedUrl);
        Http::assertSentCount(3);
    }

    #[DataProvider('localPreconditions')]
    public function test_local_preconditions_block_before_attempt_and_http(
        string $scenario,
        string $expectedCode,
    ): void {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        [$user, $brand, $draft, $publication, $connection] = $this->publication();
        $media = PublicationMedia::query()->where('draft_id', $draft->getKey())->sole();

        if ($scenario === 'missing_media') {
            $media->delete();
        } elseif ($scenario === 'unapproved_media') {
            $media->update(['status' => PublicationMedia::STATUS_UPLOADED, 'approved_at' => null]);
        } elseif ($scenario === 'invalid_media_url') {
            $media->update(['public_url' => 'http://socialflow-ai.ddev.site/image.jpg']);
        } elseif ($scenario === 'invalid_media_type') {
            $media->update(['mime_type' => 'image/png']);
        } elseif ($scenario === 'media_brand_mismatch') {
            $otherBrand = Brand::factory()->create();
            $media->update(['brand_id' => $otherBrand->getKey()]);
        } elseif ($scenario === 'future_schedule') {
            $publication->update(['scheduled_for' => now()->addMinute()]);
        } elseif ($scenario === 'cancelled_schedule') {
            $publication->update(['status' => ScheduledPublication::STATUS_CANCELLED]);
        } elseif ($scenario === 'non_approved_draft') {
            $draft->update(['status' => Draft::STATUS_DRAFT]);
        } elseif ($scenario === 'connection_missing') {
            $connection->delete();
        } elseif ($scenario === 'connection_unverified') {
            $connection->update(['status' => MetaConnection::STATUS_CONFIGURED_UNVERIFIED]);
        } elseif ($scenario === 'token_missing') {
            $connection->update(['access_token' => null]);
        } elseif ($scenario === 'account_missing') {
            $connection->update(['instagram_account_id' => null]);
        } elseif ($scenario === 'preflight_expired') {
            $media->update(['preflight_checked_at' => now()->subMinutes(16)]);
        }

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame($expectedCode, $result->errorCode);
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, string}> */
    public static function localPreconditions(): array
    {
        return [
            'missing media' => ['missing_media', 'MISSING_MEDIA_ASSET'],
            'unapproved media' => ['unapproved_media', 'MEDIA_NOT_APPROVED'],
            'invalid media URL' => ['invalid_media_url', 'MEDIA_NOT_PUBLICLY_ACCESSIBLE'],
            'invalid media type' => ['invalid_media_type', 'MEDIA_INVALID'],
            'media brand mismatch' => ['media_brand_mismatch', 'MEDIA_OWNERSHIP_MISMATCH'],
            'future schedule' => ['future_schedule', 'SCHEDULE_NOT_READY'],
            'cancelled schedule' => ['cancelled_schedule', 'SCHEDULE_CANCELLED'],
            'non-approved draft' => ['non_approved_draft', 'DRAFT_NOT_APPROVED'],
            'missing connection' => ['connection_missing', 'META_CONNECTION_MISSING'],
            'unverified connection' => ['connection_unverified', 'META_CONNECTION_UNVERIFIED'],
            'missing token' => ['token_missing', 'META_TOKEN_MISSING'],
            'missing account ID' => ['account_missing', 'META_ACCOUNT_MISSING'],
            'expired preflight' => ['preflight_expired', 'MEDIA_PREFLIGHT_REQUIRED'],
        ];
    }

    public function test_cross_tenant_actor_gets_not_found_before_attempt_or_http(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        [, , , $publication] = $this->publication();
        $otherUser = User::factory()->create();

        try {
            $this->orchestrator()->publish($otherUser, $publication);
            $this->fail('Expected a cross-tenant publication to be hidden.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, PublicationAttempt::query()->count());
        }

        Http::assertNothingSent();
    }

    public function test_create_failure_persists_only_sanitized_error(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['error' => ['message' => 'raw fake-meta-token']], 400),
        ]);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('META_CREATE_FAILED', $attempt->last_error_code);
        $this->assertSame('Instagram rechazó la creación del contenedor.', $attempt->last_error_message);
        $this->assertNull($attempt->external_container_id);
        $this->assertNull($attempt->external_media_id);
        $this->assertNotNull($attempt->completed_at);
        $this->assertStringNotContainsString('fake-meta-token', $attempt->toJson());
        $this->assertStringNotContainsString('raw', $attempt->toJson());
        Http::assertSentCount(1);
    }

    public function test_publish_failure_preserves_container_without_media_id(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_123']),
            self::STATUS_URL.'*' => Http::response(['status_code' => 'FINISHED']),
            self::PUBLISH_URL => Http::response(['error' => ['message' => 'raw details']], 400),
        ]);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('META_PUBLISH_FAILED', $attempt->last_error_code);
        $this->assertSame('container_123', $attempt->external_container_id);
        $this->assertNull($attempt->external_media_id);
        $this->assertStringNotContainsString('raw details', $attempt->toJson());
        Http::assertSentCount(3);
    }

    public function test_ambiguous_publish_failure_is_persisted_and_never_retried_automatically(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_123']),
            self::STATUS_URL.'*' => Http::response(['status_code' => 'FINISHED']),
            self::PUBLISH_URL => Http::failedConnection('raw uncertain network detail'),
        ]);
        [$user, , $draft, $publication] = $this->publication();

        $first = $this->orchestrator()->publish($user, $publication);
        $media = PublicationMedia::query()->where('draft_id', $draft->getKey())->sole();
        $media->update([
            'public_url' => 'https://cdn.example.com/replacement.jpg',
            'status' => PublicationMedia::STATUS_UPLOADED,
            'approved_at' => null,
        ]);
        $second = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($first->successful);
        $this->assertTrue($first->outcomeUncertain);
        $this->assertSame('META_PUBLISH_OUTCOME_UNKNOWN', $first->errorCode);
        $this->assertSame($first->errorCode, $second->errorCode);
        $this->assertTrue($second->outcomeUncertain);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_OUTCOME_UNKNOWN, $attempt->status);
        $this->assertSame('container_123', $attempt->external_container_id);
        $this->assertNull($attempt->external_media_id);
        $this->assertNull($attempt->published_at);
        $this->assertSame('https://cdn.example.com/approved.jpg', $attempt->media_url_snapshot);
        $this->assertStringNotContainsString('raw uncertain network detail', $attempt->toJson());
        Http::assertSentCount(3);
    }

    #[DataProvider('ambiguousPublishResponses')]
    public function test_ambiguous_publish_responses_persist_outcome_unknown(mixed $body, int $status): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_ambiguous']),
            'https://graph.instagram.com/v26.0/container_ambiguous*' => Http::response(['status_code' => 'FINISHED']),
            self::PUBLISH_URL => Http::response($body, $status, ['Content-Type' => 'application/json']),
        ]);
        [$user, , , $publication] = $this->publication();

        $result = $this->orchestrator()->publish($user, $publication);

        $this->assertFalse($result->successful);
        $this->assertSame('META_PUBLISH_OUTCOME_UNKNOWN', $result->errorCode);
        $this->assertTrue($result->outcomeUncertain);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_OUTCOME_UNKNOWN, $attempt->status);
        $this->assertSame('container_ambiguous', $attempt->external_container_id);
        $this->assertNull($attempt->external_media_id);
        $this->assertNull($attempt->published_at);
        $this->assertSame('META_PUBLISH_OUTCOME_UNKNOWN', $attempt->last_error_code);
        $this->assertStringNotContainsString('fake-meta-token', $attempt->toJson());
        Http::assertSentCount(3);
    }

    public static function ambiguousPublishResponses(): array
    {
        return [
            'server error' => [
                ['error' => ['message' => 'raw fake-meta-token']],
                500,
            ],
            'malformed json' => [
                '{invalid-json fake-meta-token',
                200,
            ],
            'missing media id' => [
                ['status' => 'published', 'detail' => 'raw fake-meta-token'],
                200,
            ],
        ];
    }

    public function test_database_unique_constraint_blocks_duplicate_target_attempt(): void
    {
        [$user, , , $publication, $connection] = $this->publication();
        $this->attempt($publication, $connection, PublicationAttempt::STATUS_PUBLISHING);

        try {
            PublicationAttempt::create([
                'scheduled_publication_id' => $publication->getKey(),
                'meta_connection_id' => $connection->getKey(),
                'initiated_by' => $user->getKey(),
                'provider' => 'meta',
                'status' => PublicationAttempt::STATUS_PUBLISHING,
                'attempt_count' => 1,
                'idempotency_key' => str_repeat('b', 64),
                'target_account_id_snapshot' => 'ig_123',
                'caption_snapshot' => 'Contenido aprobado',
                'media_url_snapshot' => 'https://cdn.example.com/approved.jpg',
                'started_at' => now(),
            ]);
            $this->fail('Expected the database idempotency constraint to reject the duplicate.');
        } catch (QueryException) {
            $this->assertSame(1, PublicationAttempt::query()->count());
        }
    }

    public function test_ready_state_alone_never_creates_attempt_or_sends_http(): void
    {
        Http::preventStrayRequests();
        [, , , $publication] = $this->publication();

        $this->assertTrue($publication->isReady());
        $this->assertSame(ScheduledPublication::STATUS_SCHEDULED, $publication->status);
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    private function orchestrator(): PublicationOrchestrator
    {
        return $this->app->make(PublicationOrchestrator::class);
    }

    /** @return array{User, Brand, Draft, ScheduledPublication, MetaConnection} */
    private function publication(): array
    {
        $this->travelTo('2026-10-04 12:00:00');
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
            'approved_at' => '2026-10-04 10:00:00',
        ]);
        $publication = ScheduledPublication::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'scheduled_by' => $user->getKey(),
            'scheduled_for' => '2026-10-04 11:00:00',
            'status' => ScheduledPublication::STATUS_SCHEDULED,
        ]);
        $connection = MetaConnection::create([
            'brand_id' => $brand->getKey(),
            'configured_by' => $user->getKey(),
            'instagram_account_id' => 'ig_123',
            'access_token' => 'fake-meta-token',
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => '2026-10-04 10:00:00',
        ]);
        PublicationMedia::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'uploaded_by' => $user->getKey(),
            'type' => PublicationMedia::TYPE_IMAGE,
            'original_filename' => 'approved.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'publication-media/'.$brand->getKey().'/approved.jpg',
            'public_url' => 'https://cdn.example.com/approved.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1080,
            'height' => 1080,
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => '2026-10-04 10:00:00',
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => 'https://cdn.example.com/approved.jpg',
            'preflight_content_type' => 'image/jpeg',
            'preflight_content_length' => 1024,
        ]);

        return [$user, $brand, $draft, $publication, $connection];
    }

    /** @param array<string, mixed> $attributes */
    private function attempt(
        ScheduledPublication $publication,
        MetaConnection $connection,
        string $status,
        array $attributes = [],
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
            'media_url_snapshot' => 'https://cdn.example.com/approved.jpg',
            'started_at' => now(),
            ...$attributes,
        ]);
    }
}
