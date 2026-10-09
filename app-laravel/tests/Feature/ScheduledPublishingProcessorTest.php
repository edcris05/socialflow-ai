<?php

namespace Tests\Feature;

use App\Contracts\MetaPublisherInterface;
use App\Models\Brand;
use App\Models\BrandAutopublishingSetting;
use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\PublicMediaHosting;
use App\Models\ScheduledPublication;
use App\Models\User;
use App\Services\Publishing\DueScheduledPublicationFinder;
use App\Services\Publishing\PublicationResult;
use App\Services\Publishing\ScheduledPublishingProcessor;
use App\Services\Publishing\ScheduledPublishingResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeMetaPublisher;
use Tests\TestCase;

class ScheduledPublishingProcessorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_future_schedule_is_skipped_without_attempt_or_publisher_call(): void
    {
        $fake = $this->fakePublisher();
        [, , , $publication] = $this->eligiblePublication();
        $publication->update(['scheduled_for' => now()->addSecond()]);

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_SKIPPED, $result->status);
        $this->assertSame('SCHEDULE_NOT_READY', $result->code);
        $this->assertSame(0, PublicationAttempt::query()->count());
        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->publishCalls);
        Http::assertNothingSent();
    }

    public function test_cancelled_schedule_is_skipped_without_attempt_or_publisher_call(): void
    {
        $fake = $this->fakePublisher();
        [, , , $publication] = $this->eligiblePublication();
        $publication->update([
            'status' => ScheduledPublication::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_SKIPPED, $result->status);
        $this->assertSame('SCHEDULE_CANCELLED', $result->code);
        $this->assertSame(0, PublicationAttempt::query()->count());
        $this->assertSame(0, $fake->createCalls);
        Http::assertNothingSent();
    }

    #[DataProvider('blockedPreconditions')]
    public function test_preconditions_block_before_attempt_or_publisher_call(
        string $scenario,
        string $expectedCode,
    ): void {
        $fake = $this->fakePublisher();
        [, , $draft, $publication, $connection, $media] = $this->eligiblePublication();

        if ($scenario === 'unapproved_draft') {
            $draft->update(['status' => Draft::STATUS_DRAFT]);
        } elseif ($scenario === 'missing_media') {
            $media->delete();
        } elseif ($scenario === 'unapproved_media') {
            $media->update(['status' => PublicationMedia::STATUS_UPLOADED]);
        } elseif ($scenario === 'missing_public_url') {
            $media->update(['public_url' => null]);
        } elseif ($scenario === 'stale_preflight') {
            $media->update(['preflight_checked_at' => now()->subMinutes(16)]);
        } elseif ($scenario === 'failed_preflight') {
            $media->update(['preflight_status' => PublicationMedia::PREFLIGHT_FAILED]);
        } elseif ($scenario === 'unverified_connection') {
            $connection->update(['status' => MetaConnection::STATUS_CONFIGURED_UNVERIFIED]);
        } elseif ($scenario === 'draft_brand_mismatch') {
            $draft->update(['brand_id' => Brand::factory()->create()->getKey()]);
        } elseif ($scenario === 'media_brand_mismatch') {
            $media->update(['brand_id' => Brand::factory()->create()->getKey()]);
        }

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_BLOCKED, $result->status);
        $this->assertSame($expectedCode, $result->code);
        $this->assertSame(0, PublicationAttempt::query()->count());
        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->publishCalls);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, string}> */
    public static function blockedPreconditions(): array
    {
        return [
            'unapproved draft' => ['unapproved_draft', 'DRAFT_NOT_APPROVED'],
            'missing media' => ['missing_media', 'MISSING_MEDIA_ASSET'],
            'unapproved media' => ['unapproved_media', 'MEDIA_NOT_APPROVED'],
            'missing public URL' => ['missing_public_url', 'MEDIA_NOT_PUBLICLY_ACCESSIBLE'],
            'stale preflight' => ['stale_preflight', 'MEDIA_PREFLIGHT_REQUIRED'],
            'failed preflight' => ['failed_preflight', 'MEDIA_PREFLIGHT_REQUIRED'],
            'unverified connection' => ['unverified_connection', 'META_CONNECTION_UNVERIFIED'],
            'draft brand mismatch' => ['draft_brand_mismatch', 'BRAND_MISMATCH'],
            'media brand mismatch' => ['media_brand_mismatch', 'MEDIA_OWNERSHIP_MISMATCH'],
        ];
    }

    #[DataProvider('disabledGateStates')]
    public function test_both_feature_gates_are_required(
        bool $metaEnabled,
        bool $scheduledEnabled,
        string $expectedCode,
    ): void {
        $fake = $this->fakePublisher();
        Config::set('services.meta.publishing_enabled', $metaEnabled);
        Config::set('services.scheduled_publishing.enabled', $scheduledEnabled);
        [, , , $publication] = $this->eligiblePublication(setGates: false);

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_BLOCKED, $result->status);
        $this->assertSame($expectedCode, $result->code);
        $this->assertSame(0, PublicationAttempt::query()->count());
        $this->assertSame(0, $fake->createCalls);
        Http::assertNothingSent();
    }

    /** @return array<string, array{bool, bool, string}> */
    public static function disabledGateStates(): array
    {
        return [
            'scheduled gate disabled' => [true, false, 'SCHEDULED_PUBLISHING_DISABLED'],
            'Meta gate disabled' => [false, true, 'PUBLISHING_DISABLED'],
            'both gates disabled' => [false, false, 'SCHEDULED_PUBLISHING_DISABLED'],
        ];
    }

    public function test_disabled_brand_is_blocked_before_publisher_call(): void
    {
        $fake = $this->fakePublisher();
        [, $brand, , $publication] = $this->eligiblePublication();
        $brand->autopublishingSetting->update(['enabled' => false]);

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_BLOCKED, $result->status);
        $this->assertSame('BRAND_AUTOPUBLISH_DISABLED', $result->code);
        $this->assertSame(0, PublicationAttempt::query()->count());
        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->statusCalls);
        $this->assertSame(0, $fake->publishCalls);
        Http::assertNothingSent();
    }

    public function test_candidate_selected_while_enabled_is_blocked_if_brand_is_disabled_before_processing(): void
    {
        $fake = $this->fakePublisher();
        [$user, $brand, , $publication] = $this->eligiblePublication();
        $candidate = $this->app->make(DueScheduledPublicationFinder::class)->find(100)->sole();
        $brand->autopublishingSetting->disable($user);

        $result = $this->processor()->process($candidate);

        $this->assertSame($publication->getKey(), $candidate->getKey());
        $this->assertSame(ScheduledPublishingResult::STATUS_BLOCKED, $result->status);
        $this->assertSame('BRAND_AUTOPUBLISH_DISABLED', $result->code);
        $this->assertSame(0, PublicationAttempt::query()->count());
        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->statusCalls);
        $this->assertSame(0, $fake->publishCalls);
        Http::assertNothingSent();
    }

    public function test_due_eligible_schedule_publishes_once_and_persists_system_attempt(): void
    {
        $fake = $this->fakePublisher();
        [, , , $publication] = $this->eligiblePublication();

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_PUBLISHED, $result->status);
        $this->assertSame(1, $fake->createCalls);
        $this->assertSame(1, $fake->publishCalls);
        $attempt = PublicationAttempt::query()->sole();
        $this->assertSame(PublicationAttempt::STATUS_PUBLISHED, $attempt->status);
        $this->assertNull($attempt->initiated_by);
        $this->assertSame('fake_container', $attempt->external_container_id);
        $this->assertSame('fake_media', $attempt->external_media_id);
        Http::assertNothingSent();
    }

    public function test_repeated_processing_skips_published_attempt_without_republishing(): void
    {
        $fake = $this->fakePublisher();
        [, , , $publication] = $this->eligiblePublication();

        $first = $this->processor()->process($publication);
        $second = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_PUBLISHED, $first->status);
        $this->assertSame(ScheduledPublishingResult::STATUS_SKIPPED, $second->status);
        $this->assertSame('ALREADY_PUBLISHED', $second->code);
        $this->assertSame(1, PublicationAttempt::query()->count());
        $this->assertSame(1, $fake->createCalls);
        $this->assertSame(1, $fake->publishCalls);
        Http::assertNothingSent();
    }

    #[DataProvider('blockingAttemptStates')]
    public function test_existing_attempt_prevents_retry(
        string $attemptStatus,
        string $expectedStatus,
        string $expectedCode,
    ): void {
        $fake = $this->fakePublisher();
        [, , , $publication, $connection] = $this->eligiblePublication();
        $this->attempt($publication, $connection, $attemptStatus);

        $result = $this->processor()->process($publication);

        $this->assertSame($expectedStatus, $result->status);
        $this->assertSame($expectedCode, $result->code);
        $this->assertSame(1, PublicationAttempt::query()->count());
        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->publishCalls);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, string, string}> */
    public static function blockingAttemptStates(): array
    {
        return [
            'published' => [PublicationAttempt::STATUS_PUBLISHED, ScheduledPublishingResult::STATUS_SKIPPED, 'ALREADY_PUBLISHED'],
            'publishing' => [PublicationAttempt::STATUS_PUBLISHING, ScheduledPublishingResult::STATUS_BLOCKED, 'PUBLICATION_IN_PROGRESS'],
            'failed' => [PublicationAttempt::STATUS_FAILED, ScheduledPublishingResult::STATUS_FAILED, 'META_CREATE_FAILED'],
            'outcome unknown' => [PublicationAttempt::STATUS_OUTCOME_UNKNOWN, ScheduledPublishingResult::STATUS_OUTCOME_UNKNOWN, 'META_PUBLISH_OUTCOME_UNKNOWN'],
        ];
    }

    public function test_publisher_failure_uses_existing_failed_attempt_semantics(): void
    {
        $fake = $this->fakePublisher();
        $fake->createResult = PublicationResult::failed('META_CREATE_FAILED', 'Safe failure.');
        [, , , $publication] = $this->eligiblePublication();

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_FAILED, $result->status);
        $this->assertSame('META_CREATE_FAILED', $result->code);
        $this->assertSame(PublicationAttempt::STATUS_FAILED, PublicationAttempt::query()->sole()->status);
        $this->assertSame(1, $fake->createCalls);
        $this->assertSame(0, $fake->publishCalls);
        Http::assertNothingSent();
    }

    public function test_ambiguous_publish_result_preserves_outcome_unknown_without_retry(): void
    {
        $fake = $this->fakePublisher();
        $fake->publishResult = PublicationResult::failed(
            'META_PUBLISH_OUTCOME_UNKNOWN',
            'Safe uncertain result.',
            outcomeUncertain: true,
            externalContainerId: 'fake_container',
        );
        [, , , $publication] = $this->eligiblePublication();

        $first = $this->processor()->process($publication);
        $second = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_OUTCOME_UNKNOWN, $first->status);
        $this->assertSame(ScheduledPublishingResult::STATUS_OUTCOME_UNKNOWN, $second->status);
        $this->assertSame(PublicationAttempt::STATUS_OUTCOME_UNKNOWN, PublicationAttempt::query()->sole()->status);
        $this->assertSame(1, $fake->createCalls);
        $this->assertSame(1, $fake->publishCalls);
        Http::assertNothingSent();
    }

    public function test_due_boundary_uses_application_time_and_requires_no_persisted_ready_status(): void
    {
        $fake = $this->fakePublisher();
        [, , , $publication] = $this->eligiblePublication();
        $publication->update(['scheduled_for' => now()]);

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_PUBLISHED, $result->status);
        $this->assertSame(ScheduledPublication::STATUS_SCHEDULED, $publication->fresh()->status);
        $this->assertSame(1, $fake->createCalls);
        Http::assertNothingSent();
    }

    public function test_stale_preflight_is_not_refreshed_and_media_is_not_hosted_automatically(): void
    {
        $fake = $this->fakePublisher();
        [, , , $publication, , $media] = $this->eligiblePublication();
        $checkedAt = now()->subMinutes(16);
        $media->update(['preflight_checked_at' => $checkedAt]);

        $result = $this->processor()->process($publication);

        $this->assertSame(ScheduledPublishingResult::STATUS_BLOCKED, $result->status);
        $this->assertSame('MEDIA_PREFLIGHT_REQUIRED', $result->code);
        $this->assertSame($checkedAt->toDateTimeString(), $media->fresh()->preflight_checked_at?->toDateTimeString());
        $this->assertSame(0, PublicMediaHosting::query()->count());
        $this->assertSame(0, $fake->createCalls);
        Http::assertNothingSent();
    }

    private function fakePublisher(): FakeMetaPublisher
    {
        Http::preventStrayRequests();
        $fake = new FakeMetaPublisher;
        $this->app->instance(MetaPublisherInterface::class, $fake);

        return $fake;
    }

    private function processor(): ScheduledPublishingProcessor
    {
        return $this->app->make(ScheduledPublishingProcessor::class);
    }

    /** @return array{User, Brand, Draft, ScheduledPublication, MetaConnection, PublicationMedia} */
    private function eligiblePublication(bool $setGates = true): array
    {
        $this->travelTo('2026-10-06 12:00:00');

        if ($setGates) {
            Config::set('services.meta.publishing_enabled', true);
            Config::set('services.scheduled_publishing.enabled', true);
        }

        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        BrandAutopublishingSetting::factory()->enabled()->for($brand)->create([
            'enabled_by' => $user->getKey(),
        ]);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => 'Publicación programada',
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
            'instagram_account_id' => 'ig_fake',
            'access_token' => 'fake-worker-token',
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => now()->subHour(),
        ]);
        $media = PublicationMedia::create([
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
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now()->subHour(),
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => 'https://cdn.example.com/approved.jpg',
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
            'initiated_by' => null,
            'provider' => 'meta',
            'status' => $status,
            'attempt_count' => 1,
            'idempotency_key' => hash('sha256', 'meta|'.$publication->getKey().'|ig_fake'),
            'target_account_id_snapshot' => 'ig_fake',
            'caption_snapshot' => 'Contenido aprobado',
            'media_url_snapshot' => 'https://cdn.example.com/approved.jpg',
            'external_container_id' => in_array($status, [PublicationAttempt::STATUS_PUBLISHED, PublicationAttempt::STATUS_OUTCOME_UNKNOWN], true)
                ? 'existing_container'
                : null,
            'external_media_id' => $status === PublicationAttempt::STATUS_PUBLISHED ? 'existing_media' : null,
            'last_error_code' => match ($status) {
                PublicationAttempt::STATUS_FAILED => 'META_CREATE_FAILED',
                PublicationAttempt::STATUS_OUTCOME_UNKNOWN => 'META_PUBLISH_OUTCOME_UNKNOWN',
                default => null,
            },
            'last_error_message' => 'Safe existing result.',
            'started_at' => now(),
        ]);
    }
}
