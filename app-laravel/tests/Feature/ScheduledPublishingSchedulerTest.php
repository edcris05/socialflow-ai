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
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeMetaPublisher;
use Tests\TestCase;

class ScheduledPublishingSchedulerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_scheduler_registers_worker_once_every_minute_with_overlap_protection(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('socialflow:publish-due --limit=25')
            ->assertSuccessful();

        $events = $this->scheduledPublishingEvents();

        $this->assertCount(1, $events);
        $event = $events->sole();
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(120, $event->expiresAt);
        $this->assertFalse($event->onOneServer);
        $this->assertSame(config('app.timezone'), $event->timezone);
    }

    public function test_overlap_ttl_exceeds_bounded_batch_meta_timeout_budget(): void
    {
        $event = $this->scheduledPublishingEvents()->sole();
        $maxChecks = (int) config('services.meta.container_status_max_attempts');
        $requestTimeoutSeconds = (int) config('services.meta.timeout');
        $pollIntervalMilliseconds = (int) config('services.meta.container_status_poll_interval_ms');
        $perPublicationSeconds = $requestTimeoutSeconds
            + ($maxChecks * $requestTimeoutSeconds)
            + intdiv(($maxChecks - 1) * $pollIntervalMilliseconds, 1000)
            + $requestTimeoutSeconds;
        $batchSeconds = 25 * $perPublicationSeconds;
        $lockSeconds = $event->expiresAt * 60;

        $this->assertSame(258, $perPublicationSeconds);
        $this->assertSame(6450, $batchSeconds);
        $this->assertSame(7200, $lockSeconds);
        $this->assertSame(750, $lockSeconds - $batchSeconds);
        $this->assertGreaterThan($batchSeconds, $lockSeconds);
    }

    public function test_registered_command_with_gates_off_makes_no_attempts_or_external_calls(): void
    {
        $this->travelTo('2026-10-06 12:00:00');
        Http::preventStrayRequests();
        Config::set('services.meta.publishing_enabled', false);
        Config::set('services.scheduled_publishing.enabled', false);
        $fake = new FakeMetaPublisher;
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$publication, $media] = $this->publication('Scheduler private caption');
        $scheduledFor = $publication->scheduled_for?->toDateTimeString();
        $preflightCheckedAt = $media->preflight_checked_at?->toDateTimeString();

        $this->artisan('socialflow:publish-due', ['--limit' => 25])
            ->expectsOutputToContain('examined=1 published=0 skipped=0 blocked=1 failed=0 outcome_unknown=0')
            ->doesntExpectOutputToContain('Scheduler private caption')
            ->doesntExpectOutputToContain('scheduler-private-token')
            ->assertSuccessful();

        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->publishCalls);
        $this->assertSame(0, PublicationAttempt::query()->count());
        $this->assertSame(0, PublicMediaHosting::query()->count());
        $this->assertSame(ScheduledPublication::STATUS_SCHEDULED, $publication->fresh()->status);
        $this->assertSame($scheduledFor, $publication->fresh()->scheduled_for?->toDateTimeString());
        $this->assertSame($preflightCheckedAt, $media->fresh()->preflight_checked_at?->toDateTimeString());
        Http::assertNothingSent();
    }

    public function test_no_eligible_records_preserves_terminal_history_and_stale_preflight(): void
    {
        $this->travelTo('2026-10-06 12:00:00');
        Http::preventStrayRequests();
        Config::set('services.meta.publishing_enabled', false);
        Config::set('services.scheduled_publishing.enabled', false);
        $fake = new FakeMetaPublisher;
        $this->app->instance(MetaPublisherInterface::class, $fake);
        [$published] = $this->publication('Published history');
        [$outcomeUnknown] = $this->publication('Unknown history');
        [$stale, $staleMedia] = $this->publication('Stale preflight');
        $publishedAttempt = $this->attempt($published, PublicationAttempt::STATUS_PUBLISHED);
        $unknownAttempt = $this->attempt($outcomeUnknown, PublicationAttempt::STATUS_OUTCOME_UNKNOWN);
        $staleMedia->update(['preflight_checked_at' => now()->subMinutes(16)]);
        $staleCheckedAt = $staleMedia->fresh()->preflight_checked_at?->toDateTimeString();

        $this->artisan('socialflow:publish-due', ['--limit' => 25])
            ->expectsOutputToContain('examined=0 published=0 skipped=0 blocked=0 failed=0 outcome_unknown=0')
            ->assertSuccessful();

        $this->assertSame(PublicationAttempt::STATUS_PUBLISHED, $publishedAttempt->fresh()->status);
        $this->assertSame(PublicationAttempt::STATUS_OUTCOME_UNKNOWN, $unknownAttempt->fresh()->status);
        $this->assertSame(2, PublicationAttempt::query()->count());
        $this->assertSame(ScheduledPublication::STATUS_SCHEDULED, $published->fresh()->status);
        $this->assertSame(ScheduledPublication::STATUS_SCHEDULED, $outcomeUnknown->fresh()->status);
        $this->assertSame(ScheduledPublication::STATUS_SCHEDULED, $stale->fresh()->status);
        $this->assertSame($staleCheckedAt, $staleMedia->fresh()->preflight_checked_at?->toDateTimeString());
        $this->assertSame(0, PublicMediaHosting::query()->count());
        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->publishCalls);
        Http::assertNothingSent();
    }

    /** @return Collection<int, Event> */
    private function scheduledPublishingEvents(): Collection
    {
        return collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains(
                (string) $event->command,
                'socialflow:publish-due --limit=25',
            ))
            ->values();
    }

    /** @return array{ScheduledPublication, PublicationMedia} */
    private function publication(string $caption): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => $caption,
            'content' => $caption,
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
        MetaConnection::create([
            'brand_id' => $brand->getKey(),
            'configured_by' => $user->getKey(),
            'instagram_account_id' => 'ig_'.$brand->getKey(),
            'access_token' => 'scheduler-private-token',
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
            'public_url' => 'https://cdn.example.com/'.$draft->getKey().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now()->subHour(),
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => 'https://cdn.example.com/'.$draft->getKey().'.jpg',
            'preflight_content_type' => 'image/jpeg',
            'preflight_content_length' => 1024,
        ]);

        return [$publication, $media];
    }

    private function attempt(ScheduledPublication $publication, string $status): PublicationAttempt
    {
        $connection = $publication->brand->metaConnection;

        return PublicationAttempt::create([
            'scheduled_publication_id' => $publication->getKey(),
            'meta_connection_id' => $connection->getKey(),
            'initiated_by' => null,
            'provider' => 'meta',
            'status' => $status,
            'attempt_count' => 1,
            'idempotency_key' => hash('sha256', 'meta|'.$publication->getKey().'|'.$connection->instagram_account_id),
            'target_account_id_snapshot' => $connection->instagram_account_id,
            'caption_snapshot' => $publication->draft->content,
            'media_url_snapshot' => $publication->draft->currentPublicationMedia->effectivePublicUrl(),
            'external_container_id' => 'historical_container_'.$publication->getKey(),
            'external_media_id' => $status === PublicationAttempt::STATUS_PUBLISHED
                ? 'historical_media_'.$publication->getKey()
                : null,
            'last_error_code' => $status === PublicationAttempt::STATUS_OUTCOME_UNKNOWN
                ? 'META_PUBLISH_OUTCOME_UNKNOWN'
                : null,
            'last_error_message' => $status === PublicationAttempt::STATUS_OUTCOME_UNKNOWN
                ? 'Safe uncertain result.'
                : null,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'published_at' => $status === PublicationAttempt::STATUS_PUBLISHED ? now()->subHour() : null,
        ]);
    }
}
