<?php

namespace Tests\Feature;

use App\Contracts\MetaPublisherInterface;
use App\Models\Brand;
use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\ScheduledPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeMetaPublisher;
use Tests\TestCase;

class PublishDueScheduledPublicationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_respects_limit_and_processes_due_schedules_in_deterministic_order(): void
    {
        $fake = $this->fakePublisher();
        $this->enableGates();
        $this->publication('Third', '2026-10-06 11:30:00');
        $this->publication('First', '2026-10-06 10:30:00');
        $this->publication('Second', '2026-10-06 11:00:00');

        $this->artisan('socialflow:publish-due', ['--limit' => 2])
            ->expectsOutputToContain('examined=2 published=2 skipped=0 blocked=0 failed=0 outcome_unknown=0')
            ->doesntExpectOutputToContain('command-secret-token')
            ->assertSuccessful();

        $this->assertSame(['First', 'Second'], $fake->captions);
        $this->assertSame(2, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_command_uses_configured_default_limit_and_caps_explicit_limit(): void
    {
        $fake = $this->fakePublisher();
        $this->enableGates();
        Config::set('services.scheduled_publishing.default_limit', 1);
        Config::set('services.scheduled_publishing.max_limit', 2);
        $this->publication('First', '2026-10-06 10:30:00');
        $this->publication('Second', '2026-10-06 11:00:00');
        $this->publication('Third', '2026-10-06 11:30:00');

        $this->artisan('socialflow:publish-due')
            ->expectsOutputToContain('examined=1 published=1')
            ->assertSuccessful();
        $this->artisan('socialflow:publish-due', ['--limit' => 99])
            ->expectsOutputToContain('examined=2 published=2 skipped=0')
            ->assertSuccessful();

        $this->assertSame(['First', 'Second', 'Third'], $fake->captions);
        $this->assertSame(3, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_command_discovers_only_due_scheduled_records(): void
    {
        $fake = $this->fakePublisher();
        $this->enableGates();
        $this->publication('Due', '2026-10-06 11:00:00');
        $this->publication('Future', '2026-10-06 12:00:01');
        $cancelled = $this->publication('Cancelled', '2026-10-06 10:00:00');
        $cancelled->update(['status' => ScheduledPublication::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $this->artisan('socialflow:publish-due', ['--limit' => 10])
            ->expectsOutputToContain('examined=1 published=1 skipped=0 blocked=0 failed=0 outcome_unknown=0')
            ->assertSuccessful();

        $this->assertSame(['Due'], $fake->captions);
        $this->assertSame(1, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_command_reports_blocked_summary_with_scheduled_gate_off_by_default(): void
    {
        $fake = $this->fakePublisher();
        Config::set('services.meta.publishing_enabled', true);
        Config::set('services.scheduled_publishing.enabled', false);
        $this->publication('Blocked', '2026-10-06 11:00:00');

        $this->artisan('socialflow:publish-due')
            ->expectsOutputToContain('examined=1 published=0 skipped=0 blocked=1 failed=0 outcome_unknown=0')
            ->doesntExpectOutputToContain('command-secret-token')
            ->assertSuccessful();

        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_command_rejects_invalid_limit_without_processing(): void
    {
        $fake = $this->fakePublisher();
        $this->enableGates();
        $this->publication('Due', '2026-10-06 11:00:00');

        $this->artisan('socialflow:publish-due', ['--limit' => 0])
            ->expectsOutputToContain('The --limit option must be a positive integer.')
            ->assertExitCode(2);
        $this->artisan('socialflow:publish-due', ['--limit' => 'invalid'])
            ->expectsOutputToContain('The --limit option must be a positive integer.')
            ->assertExitCode(2);

        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_repeated_command_does_not_republish_completed_schedule(): void
    {
        $fake = $this->fakePublisher();
        $this->enableGates();
        $this->publication('Once', '2026-10-06 11:00:00');

        $this->artisan('socialflow:publish-due')
            ->expectsOutputToContain('examined=1 published=1 skipped=0')
            ->assertSuccessful();
        $this->artisan('socialflow:publish-due')
            ->expectsOutputToContain('examined=0 published=0 skipped=0')
            ->assertSuccessful();

        $this->assertSame(1, $fake->createCalls);
        $this->assertSame(1, $fake->publishCalls);
        $this->assertSame(1, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    #[DataProvider('terminalAttemptStatuses')]
    public function test_terminal_historical_attempts_do_not_starve_later_eligible_schedule(
        string $attemptStatus,
    ): void {
        $fake = $this->fakePublisher();
        $this->enableGates();
        $historicalPublicationIds = [];

        for ($index = 0; $index < 30; $index++) {
            $publication = $this->publication(
                'Historical '.$index,
                now()->subHours(3)->addMinutes($index)->toDateTimeString(),
            );
            $this->attempt($publication, $attemptStatus);
            $historicalPublicationIds[] = $publication->getKey();
        }

        $this->publication('Eligible', '2026-10-06 11:45:00');

        $this->artisan('socialflow:publish-due', ['--limit' => 25])
            ->expectsOutputToContain('examined=1 published=1 skipped=0 blocked=0 failed=0 outcome_unknown=0')
            ->assertSuccessful();

        $this->assertSame(['Eligible'], $fake->captions);
        $this->assertSame(31, PublicationAttempt::query()->count());
        $this->assertSame(30, PublicationAttempt::query()
            ->whereIn('scheduled_publication_id', $historicalPublicationIds)
            ->where('status', $attemptStatus)
            ->count());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function terminalAttemptStatuses(): array
    {
        return [
            'published' => [PublicationAttempt::STATUS_PUBLISHED],
            'outcome unknown' => [PublicationAttempt::STATUS_OUTCOME_UNKNOWN],
            'failed' => [PublicationAttempt::STATUS_FAILED],
            'publishing' => [PublicationAttempt::STATUS_PUBLISHING],
        ];
    }

    public function test_stale_preflight_history_does_not_starve_later_eligible_schedule(): void
    {
        $fake = $this->fakePublisher();
        $this->enableGates();

        for ($index = 0; $index < 30; $index++) {
            $publication = $this->publication(
                'Stale '.$index,
                now()->subHours(3)->addMinutes($index)->toDateTimeString(),
            );
            $publication->draft->currentPublicationMedia->update([
                'preflight_checked_at' => now()->subMinutes(16),
            ]);
        }

        $this->publication('Eligible', '2026-10-06 11:45:00');

        $this->artisan('socialflow:publish-due', ['--limit' => 25])
            ->expectsOutputToContain('examined=1 published=1 skipped=0 blocked=0 failed=0 outcome_unknown=0')
            ->assertSuccessful();

        $this->assertSame(['Eligible'], $fake->captions);
        $this->assertSame(1, PublicationAttempt::query()->count());
        $this->assertSame(30, PublicationMedia::query()
            ->where('preflight_checked_at', '<', now()->subMinutes(15))
            ->count());
        Http::assertNothingSent();
    }

    public function test_all_repairable_blockers_terminate_without_processing_or_http(): void
    {
        $fake = $this->fakePublisher();
        $this->enableGates();

        for ($index = 0; $index < 30; $index++) {
            $publication = $this->publication(
                'Blocked '.$index,
                now()->subHours(3)->addMinutes($index)->toDateTimeString(),
            );
            $publication->draft->currentPublicationMedia->update([
                'preflight_checked_at' => now()->subMinutes(16),
            ]);
        }

        $this->artisan('socialflow:publish-due', ['--limit' => 25])
            ->expectsOutputToContain('examined=0 published=0 skipped=0 blocked=0 failed=0 outcome_unknown=0')
            ->assertSuccessful();

        $this->assertSame(0, $fake->createCalls);
        $this->assertSame(0, $fake->publishCalls);
        $this->assertSame(0, PublicationAttempt::query()->count());
        Http::assertNothingSent();
    }

    private function fakePublisher(): FakeMetaPublisher
    {
        $this->travelTo('2026-10-06 12:00:00');
        Http::preventStrayRequests();
        $fake = new FakeMetaPublisher;
        $this->app->instance(MetaPublisherInterface::class, $fake);

        return $fake;
    }

    private function enableGates(): void
    {
        Config::set('services.meta.publishing_enabled', true);
        Config::set('services.scheduled_publishing.enabled', true);
    }

    private function publication(string $caption, string $scheduledFor): ScheduledPublication
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
            'scheduled_for' => $scheduledFor,
            'status' => ScheduledPublication::STATUS_SCHEDULED,
        ]);
        MetaConnection::create([
            'brand_id' => $brand->getKey(),
            'configured_by' => $user->getKey(),
            'instagram_account_id' => 'ig_'.$brand->getKey(),
            'access_token' => 'command-secret-token',
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => now()->subHour(),
        ]);
        PublicationMedia::create([
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

        return $publication;
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
            'external_container_id' => in_array($status, [
                PublicationAttempt::STATUS_PUBLISHED,
                PublicationAttempt::STATUS_OUTCOME_UNKNOWN,
            ], true) ? 'historical_container_'.$publication->getKey() : null,
            'external_media_id' => $status === PublicationAttempt::STATUS_PUBLISHED
                ? 'historical_media_'.$publication->getKey()
                : null,
            'last_error_code' => match ($status) {
                PublicationAttempt::STATUS_FAILED => 'META_CREATE_FAILED',
                PublicationAttempt::STATUS_OUTCOME_UNKNOWN => 'META_PUBLISH_OUTCOME_UNKNOWN',
                default => null,
            },
            'last_error_message' => 'Safe historical result.',
            'started_at' => now()->subHour(),
            'completed_at' => $status === PublicationAttempt::STATUS_PUBLISHING ? null : now()->subHour(),
            'published_at' => $status === PublicationAttempt::STATUS_PUBLISHED ? now()->subHour() : null,
        ]);
    }
}
