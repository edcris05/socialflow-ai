<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandAutopublishingSetting;
use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\ScheduledPublication;
use App\Models\User;
use App\Services\Publishing\DueScheduledPublicationFinder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DueScheduledPublicationFinderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_brand_without_setting_is_excluded(): void
    {
        [, , , $publication] = $this->eligiblePublication();

        $candidates = $this->finder()->find(100);

        $this->assertCount(0, $candidates);
        $this->assertFalse($candidates->contains($publication));
    }

    public function test_disabled_brand_is_excluded(): void
    {
        [$user, $brand, , $publication] = $this->eligiblePublication();
        BrandAutopublishingSetting::factory()->for($brand)->create([
            'disabled_by' => $user->getKey(),
            'disabled_at' => now(),
        ]);

        $candidates = $this->finder()->find(100);

        $this->assertCount(0, $candidates);
        $this->assertFalse($candidates->contains($publication));
    }

    public function test_incomplete_enabled_setting_is_excluded(): void
    {
        [, $brand, , $publication] = $this->eligiblePublication();
        BrandAutopublishingSetting::factory()->for($brand)->create([
            'enabled' => true,
            'enabled_by' => null,
            'enabled_at' => null,
        ]);

        $candidates = $this->finder()->find(100);

        $this->assertCount(0, $candidates);
        $this->assertFalse($candidates->contains($publication));
    }

    public function test_enabled_eligible_brand_is_included(): void
    {
        [$user, $brand, , $publication] = $this->eligiblePublication();
        $this->enableAutopublishing($user, $brand);

        $candidates = $this->finder()->find(100);

        $this->assertCount(1, $candidates);
        $this->assertSame($publication->getKey(), $candidates->sole()->getKey());
    }

    public function test_two_due_brands_return_only_enabled_chatcito(): void
    {
        [$chatcitoUser, $chatcito, , $chatcitoPublication] = $this->eligiblePublication('Chatcito');
        [$artMadeUser, $artMade] = $this->eligiblePublication('Art Made');
        $this->enableAutopublishing($chatcitoUser, $chatcito);
        BrandAutopublishingSetting::factory()->for($artMade)->create([
            'disabled_by' => $artMadeUser->getKey(),
            'disabled_at' => now(),
        ]);

        $candidates = $this->finder()->find(100);

        $this->assertCount(1, $candidates);
        $this->assertSame($chatcitoPublication->getKey(), $candidates->sole()->getKey());
        $this->assertSame('Chatcito', $candidates->sole()->brand->name);
    }

    public function test_disabled_brands_do_not_consume_limit_before_enabled_candidate(): void
    {
        for ($index = 0; $index < 5; $index++) {
            [$user, $brand, , $publication] = $this->eligiblePublication('Disabled '.$index);
            BrandAutopublishingSetting::factory()->for($brand)->create([
                'disabled_by' => $user->getKey(),
                'disabled_at' => now(),
            ]);
            $publication->update(['scheduled_for' => now()->subHours(2)->addMinutes($index)]);
        }
        [$enabledUser, $enabledBrand, , $enabledPublication] = $this->eligiblePublication('Enabled');
        $this->enableAutopublishing($enabledUser, $enabledBrand);
        $enabledPublication->update(['scheduled_for' => now()->subMinute()]);

        $candidates = $this->finder()->find(1);

        $this->assertCount(1, $candidates);
        $this->assertSame($enabledPublication->getKey(), $candidates->sole()->getKey());
    }

    public function test_two_enabled_brands_are_eligible_in_deterministic_order(): void
    {
        [$firstUser, $firstBrand, , $firstPublication] = $this->eligiblePublication('First');
        [$secondUser, $secondBrand, , $secondPublication] = $this->eligiblePublication('Second');
        $this->enableAutopublishing($firstUser, $firstBrand);
        $this->enableAutopublishing($secondUser, $secondBrand);
        $firstPublication->update(['scheduled_for' => now()->subMinutes(2)]);
        $secondPublication->update(['scheduled_for' => now()->subMinute()]);

        $candidates = $this->finder()->find(2);

        $this->assertSame([
            $firstPublication->getKey(),
            $secondPublication->getKey(),
        ], $candidates->modelKeys());
    }

    #[DataProvider('ineligibleBrandStates')]
    public function test_enabled_brand_with_invalid_publication_state_is_excluded(string $scenario): void
    {
        [$user, $brand, $draft, $publication, $connection, $media] = $this->eligiblePublication();
        $this->enableAutopublishing($user, $brand);

        match ($scenario) {
            'draft_not_approved' => $draft->update(['status' => Draft::STATUS_DRAFT]),
            'media_not_approved' => $media->update(['status' => PublicationMedia::STATUS_UPLOADED]),
            'media_invalid' => $media->update(['mime_type' => 'image/png']),
            'stale_preflight' => $media->update(['preflight_checked_at' => now()->subMinutes(16)]),
            'meta_unverified' => $connection->update(['status' => MetaConnection::STATUS_CONFIGURED_UNVERIFIED]),
        };

        $candidates = $this->finder()->find(100);

        $this->assertCount(0, $candidates);
        $this->assertFalse($candidates->contains($publication));
    }

    /** @return array<string, array{string}> */
    public static function ineligibleBrandStates(): array
    {
        return [
            'draft not approved' => ['draft_not_approved'],
            'media not approved' => ['media_not_approved'],
            'media MIME invalid' => ['media_invalid'],
            'stale preflight' => ['stale_preflight'],
            'Meta unverified' => ['meta_unverified'],
        ];
    }

    #[DataProvider('blockingAttemptStatuses')]
    public function test_enabled_brand_with_previous_blocking_attempt_is_excluded(string $status): void
    {
        [$user, $brand, , $publication, $connection] = $this->eligiblePublication();
        $this->enableAutopublishing($user, $brand);
        $this->attempt($publication, $connection, $status);

        $candidates = $this->finder()->find(100);

        $this->assertCount(0, $candidates);
        $this->assertFalse($candidates->contains($publication));
    }

    /** @return array<string, array{string}> */
    public static function blockingAttemptStatuses(): array
    {
        return [
            'failed' => [PublicationAttempt::STATUS_FAILED],
            'outcome unknown' => [PublicationAttempt::STATUS_OUTCOME_UNKNOWN],
        ];
    }

    private function finder(): DueScheduledPublicationFinder
    {
        return $this->app->make(DueScheduledPublicationFinder::class);
    }

    private function enableAutopublishing(User $user, Brand $brand): void
    {
        BrandAutopublishingSetting::factory()->enabled()->for($brand)->create([
            'enabled_by' => $user->getKey(),
        ]);
    }

    /** @return array{User, Brand, Draft, ScheduledPublication, MetaConnection, PublicationMedia} */
    private function eligiblePublication(string $brandName = 'Eligible Brand'): array
    {
        $this->travelTo('2026-10-09 12:00:00');
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['name' => $brandName]);
        $user->brands()->attach($brand, ['role' => 'owner']);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => 'Publicación elegible',
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
            'instagram_account_id' => 'ig_'.$brand->getKey(),
            'access_token' => 'fake-finder-token',
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
            'idempotency_key' => hash('sha256', 'finder|'.$publication->getKey()),
            'target_account_id_snapshot' => $connection->instagram_account_id,
            'caption_snapshot' => $publication->draft->content,
            'media_url_snapshot' => $publication->draft->currentPublicationMedia->effectivePublicUrl(),
            'last_error_code' => $status === PublicationAttempt::STATUS_OUTCOME_UNKNOWN
                ? 'META_PUBLISH_OUTCOME_UNKNOWN'
                : 'META_CREATE_FAILED',
            'last_error_message' => 'Safe historical result.',
            'started_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);
    }
}
