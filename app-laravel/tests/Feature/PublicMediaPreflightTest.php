<?php

namespace Tests\Feature;

use App\Contracts\PublicHostResolverInterface;
use App\Models\Brand;
use App\Models\Draft;
use App\Models\PublicationMedia;
use App\Models\User;
use App\Services\Publishing\PublicMediaPreflight;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicMediaPreflightTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MEDIA_URL = 'https://media.example.com/photo.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.publication_media.allowed_hosts', ['media.example.com']);
        Config::set('services.publication_media.max_bytes', 8192);
        Http::preventStrayRequests();
        $this->resolveTo(['8.8.8.8']);
    }

    public function test_allowlisted_public_host_with_real_jpeg_signature_passes_and_persists_safe_metadata(): void
    {
        Http::fake([
            self::MEDIA_URL => Http::response("\xFF\xD8\xFFjpeg", 200, [
                'Content-Type' => 'image/jpeg; charset=binary',
                'Content-Length' => '7',
            ]),
        ]);
        [, , , $media] = $this->approvedMedia();

        $result = $this->preflight()->verifyAndRecord($media);

        $this->assertTrue($result->passed);
        $this->assertSame('image/jpeg', $result->contentType);
        $this->assertSame(7, $result->contentLength);
        $media->refresh();
        $this->assertSame(PublicationMedia::PREFLIGHT_PASSED, $media->preflight_status);
        $this->assertSame(self::MEDIA_URL, $media->preflight_final_url);
        $this->assertNull($media->preflight_error_code);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::MEDIA_URL
            && $request->method() === 'GET'
            && $request->hasHeader('Range', 'bytes=0-8192'));
        Http::assertSentCount(1);
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_or_unallowlisted_url_is_rejected_before_http(string $url, string $expectedCode): void
    {
        [, , , $media] = $this->approvedMedia(['public_url' => $url]);

        $result = $this->preflight()->verifyAndRecord($media);

        $this->assertFalse($result->passed);
        $this->assertSame($expectedCode, $result->errorCode);
        $this->assertSame(PublicationMedia::PREFLIGHT_FAILED, $media->fresh()->preflight_status);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, string}> */
    public static function unsafeUrls(): array
    {
        return [
            'host not allowlisted' => ['https://other.example.com/photo.jpg', 'MEDIA_HOST_NOT_ALLOWED'],
            'localhost' => ['https://localhost/photo.jpg', 'MEDIA_URL_UNSAFE'],
            'IPv4 loopback' => ['https://127.0.0.1/photo.jpg', 'MEDIA_URL_UNSAFE'],
            'IPv6 loopback' => ['https://[::1]/photo.jpg', 'MEDIA_URL_UNSAFE'],
            'DDEV host' => ['https://site.ddev.site/photo.jpg', 'MEDIA_URL_UNSAFE'],
            'credentials' => ['https://user:password@media.example.com/photo.jpg', 'MEDIA_URL_UNSAFE'],
            'HTTP scheme' => ['http://media.example.com/photo.jpg', 'MEDIA_URL_UNSAFE'],
            'non-standard port' => ['https://media.example.com:8443/photo.jpg', 'MEDIA_URL_UNSAFE'],
        ];
    }

    public function test_empty_allowlist_denies_before_dns_or_http(): void
    {
        Config::set('services.publication_media.allowed_hosts', []);
        [, , , $media] = $this->approvedMedia();

        $result = $this->preflight()->verifyAndRecord($media);

        $this->assertSame('MEDIA_HOST_NOT_ALLOWED', $result->errorCode);
        Http::assertNothingSent();
    }

    #[DataProvider('unsafeResolvedAddresses')]
    public function test_private_reserved_or_missing_dns_destination_is_rejected_before_http(array $addresses): void
    {
        $this->resolveTo($addresses);
        [, , , $media] = $this->approvedMedia();

        $result = $this->preflight()->verifyAndRecord($media);

        $this->assertSame('MEDIA_URL_UNSAFE', $result->errorCode);
        Http::assertNothingSent();
    }

    /** @return array<string, array{list<string>}> */
    public static function unsafeResolvedAddresses(): array
    {
        return [
            'no DNS answer' => [[]],
            'private IPv4' => [['10.0.0.4']],
            'loopback IPv4' => [['127.0.0.1']],
            'link-local IPv4' => [['169.254.1.2']],
            'private IPv6' => [['fd00::1']],
            'loopback IPv6' => [['::1']],
            'mixed public and private' => [['8.8.8.8', '192.168.1.4']],
        ];
    }

    #[DataProvider('failedResponses')]
    public function test_remote_http_and_content_failures_are_safe(string $scenario, string $expectedCode): void
    {
        $response = match ($scenario) {
            '404' => Http::response('raw-secret', 404),
            '500' => Http::response('raw-secret', 500),
            'html' => Http::response('<html>raw-secret</html>', 200, ['Content-Type' => 'text/html']),
            'png' => Http::response("\x89PNG raw-secret", 200, ['Content-Type' => 'image/png']),
            'invalid-jpeg' => Http::response('not-a-jpeg raw-secret', 200, ['Content-Type' => 'image/jpeg']),
            'redirect' => Http::response('', 302, ['Location' => self::MEDIA_URL]),
            'redirect-chain' => Http::response('', 301, ['Location' => 'https://media.example.com/again']),
            'redirect-unallowed' => Http::response('', 302, ['Location' => 'https://evil.example/']),
            'too-large' => Http::response("\xFF\xD8\xFF", 200, ['Content-Type' => 'image/jpeg', 'Content-Length' => '8193']),
        };
        Http::fake([self::MEDIA_URL => $response]);
        [, , , $media] = $this->approvedMedia();

        $result = $this->preflight()->verifyAndRecord($media);

        $this->assertFalse($result->passed);
        $this->assertSame($expectedCode, $result->errorCode);
        $this->assertNull($media->fresh()->preflight_final_url);
        $this->assertStringNotContainsString('raw-secret', $media->fresh()->toJson());
        Http::assertSentCount(1);
    }

    /** @return array<string, array{string, string}> */
    public static function failedResponses(): array
    {
        return [
            '404' => ['404', 'MEDIA_UNREACHABLE'],
            '500' => ['500', 'MEDIA_UNREACHABLE'],
            'HTML' => ['html', 'MEDIA_CONTENT_TYPE_INVALID'],
            'PNG' => ['png', 'MEDIA_CONTENT_TYPE_INVALID'],
            'fake JPEG MIME' => ['invalid-jpeg', 'MEDIA_CONTENT_INVALID'],
            'redirect' => ['redirect', 'MEDIA_REDIRECT_REJECTED'],
            'redirect chain' => ['redirect-chain', 'MEDIA_REDIRECT_REJECTED'],
            'redirect to unallowed host' => ['redirect-unallowed', 'MEDIA_REDIRECT_REJECTED'],
            'declared too large' => ['too-large', 'MEDIA_TOO_LARGE'],
        ];
    }

    public function test_timeout_is_recorded_as_safe_unreachable_failure(): void
    {
        Http::fake([self::MEDIA_URL => Http::failedConnection('raw-secret timeout')]);
        [, , , $media] = $this->approvedMedia();

        $result = $this->preflight()->verifyAndRecord($media);

        $this->assertSame('MEDIA_UNREACHABLE', $result->errorCode);
        $this->assertStringNotContainsString('raw-secret', $media->fresh()->toJson());
    }

    public function test_url_change_invalidates_a_previous_pass(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia([
            'preflight_status' => PublicationMedia::PREFLIGHT_PASSED,
            'preflight_checked_at' => now(),
            'preflight_final_url' => self::MEDIA_URL,
            'preflight_content_type' => 'image/jpeg',
            'preflight_content_length' => 7,
        ]);

        $this->actingAs($user)->patch(
            route('marcas.borradores.media.public-url.update', [$brand, $draft, $media]),
            ['public_url' => 'https://new.example.com/photo.jpg'],
        )->assertRedirect();

        $media->refresh();
        $this->assertSame(PublicationMedia::PREFLIGHT_NOT_CHECKED, $media->preflight_status);
        $this->assertNull($media->preflight_checked_at);
        $this->assertNull($media->preflight_final_url);
        Http::assertNothingSent();
    }

    public function test_rejected_media_cannot_be_preflighted(): void
    {
        [$user, $brand, $draft, $media] = $this->approvedMedia(['status' => PublicationMedia::STATUS_REJECTED]);

        $this->actingAs($user)
            ->post(route('marcas.borradores.media.preflight.store', [$brand, $draft, $media]))
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_cross_tenant_preflight_is_hidden_before_http(): void
    {
        [, $brand, $draft, $media] = $this->approvedMedia();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('marcas.borradores.media.preflight.store', [$brand, $draft, $media]))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    private function preflight(): PublicMediaPreflight
    {
        return $this->app->make(PublicMediaPreflight::class);
    }

    /** @param list<string> $addresses */
    private function resolveTo(array $addresses): void
    {
        $this->app->instance(PublicHostResolverInterface::class, new class($addresses) implements PublicHostResolverInterface
        {
            /** @param list<string> $addresses */
            public function __construct(private array $addresses) {}

            public function resolve(string $host): array
            {
                return $this->addresses;
            }
        });
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
            'title' => 'Aprobado',
            'content' => 'Contenido',
            'status' => Draft::STATUS_APPROVED,
        ]);
        $media = PublicationMedia::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'uploaded_by' => $user->getKey(),
            'type' => PublicationMedia::TYPE_IMAGE,
            'original_filename' => 'photo.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'publication-media/'.$brand->getKey().'/photo.jpg',
            'public_url' => self::MEDIA_URL,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
            ...$attributes,
        ]);

        return [$user, $brand, $draft, $media];
    }
}
