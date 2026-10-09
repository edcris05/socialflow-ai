<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\MetaConnection;
use App\Models\User;
use App\Services\Meta\InstagramMetaPublisher;
use App\Services\Meta\MetaContainerStatus;
use App\Services\Publishing\InstagramPublicationPayload;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstagramMetaPublisherTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CREATE_URL = 'https://graph.instagram.com/v26.0/ig_123/media';

    private const PUBLISH_URL = 'https://graph.instagram.com/v26.0/ig_123/media_publish';

    private const STATUS_URL = 'https://graph.instagram.com/v26.0/container_123';

    public function test_create_container_sends_approved_image_payload_and_bearer_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['id' => 'container_123']),
        ]);
        $connection = $this->connection('fake-publishing-token');

        $result = (new InstagramMetaPublisher)->createContainer(
            $connection,
            new InstagramPublicationPayload('Caption aprobado', 'https://cdn.example.com/approved.jpg'),
        );

        $this->assertTrue($result->successful);
        $this->assertSame('container_123', $result->externalContainerId);
        $this->assertNull($result->externalMediaId);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame(self::CREATE_URL, $request->url());
            $this->assertSame('Caption aprobado', $request['caption']);
            $this->assertSame('https://cdn.example.com/approved.jpg', $request['image_url']);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer fake-publishing-token'));

            return true;
        });
        Http::assertSentCount(1);
    }

    public function test_publish_container_sends_creation_id_and_returns_media_id(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLISH_URL => Http::response(['id' => 'media_123']),
        ]);
        $connection = $this->connection();

        $result = (new InstagramMetaPublisher)->publishContainer($connection, 'container_123');

        $this->assertTrue($result->successful);
        $this->assertSame('container_123', $result->externalContainerId);
        $this->assertSame('media_123', $result->externalMediaId);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame(self::PUBLISH_URL, $request->url());
            $this->assertSame('container_123', $request['creation_id']);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer fake-meta-token'));

            return true;
        });
        Http::assertSentCount(1);
    }

    #[DataProvider('containerStatuses')]
    public function test_container_status_returns_known_meta_state(
        string $statusCode,
        MetaContainerStatus $expectedStatus,
    ): void {
        Http::preventStrayRequests();
        Http::fake([
            self::STATUS_URL.'*' => Http::response([
                'status_code' => $statusCode,
                'status' => $statusCode,
            ]),
        ]);

        $result = (new InstagramMetaPublisher)->getContainerStatus($this->connection(), 'container_123');

        $this->assertTrue($result->successful);
        $this->assertSame($expectedStatus, $result->status);
        $this->assertNull($result->errorCode);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('GET', $request->method());
            $this->assertStringStartsWith(self::STATUS_URL, $request->url());
            $this->assertSame('status_code,status', $request['fields']);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer fake-meta-token'));

            return true;
        });
        Http::assertSentCount(1);
    }

    /** @return array<string, array{string, MetaContainerStatus}> */
    public static function containerStatuses(): array
    {
        return [
            'finished' => ['FINISHED', MetaContainerStatus::FINISHED],
            'finished with surrounding whitespace' => [' FINISHED ', MetaContainerStatus::FINISHED],
            'in progress' => ['IN_PROGRESS', MetaContainerStatus::IN_PROGRESS],
            'error' => ['ERROR', MetaContainerStatus::ERROR],
            'expired' => ['EXPIRED', MetaContainerStatus::EXPIRED],
            'published' => ['PUBLISHED', MetaContainerStatus::PUBLISHED],
        ];
    }

    #[DataProvider('statusHttpFailures')]
    public function test_container_status_http_failures_are_sanitized(int $httpStatus, string $expectedCode): void
    {
        $token = 'fake-status-secret-token';
        Http::preventStrayRequests();
        Http::fake([
            self::STATUS_URL.'*' => Http::response(['error' => ['message' => 'raw '.$token]], $httpStatus),
        ]);

        $result = (new InstagramMetaPublisher)->getContainerStatus($this->connection($token), 'container_123');

        $this->assertFalse($result->successful);
        $this->assertNull($result->status);
        $this->assertSame($expectedCode, $result->errorCode);
        $this->assertStringNotContainsString($token, (string) $result->errorMessage);
        $this->assertStringNotContainsString('raw', (string) $result->errorMessage);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::PUBLISH_URL);
    }

    /** @return array<string, array{int, string}> */
    public static function statusHttpFailures(): array
    {
        return [
            'bad request' => [400, 'META_CONTAINER_STATUS_FAILED'],
            'unauthorized' => [401, 'META_AUTH_FAILED'],
            'forbidden' => [403, 'META_AUTH_FAILED'],
            'server error' => [500, 'META_CONTAINER_STATUS_FAILED'],
        ];
    }

    #[DataProvider('invalidStatusResponses')]
    public function test_container_status_unknown_or_malformed_response_fails_closed(mixed $body): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::STATUS_URL.'*' => Http::response($body, 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new InstagramMetaPublisher)->getContainerStatus($this->connection(), 'container_123');

        $this->assertFalse($result->successful);
        $this->assertNull($result->status);
        $this->assertSame('META_CONTAINER_STATUS_INVALID', $result->errorCode);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::PUBLISH_URL);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidStatusResponses(): array
    {
        return [
            'malformed json' => ['{invalid-json'],
            'missing status code' => [['status' => 'FINISHED']],
            'unknown status code' => [['status_code' => 'UNKNOWN']],
            'blank status code' => [['status_code' => '']],
            'whitespace status code' => [['status_code' => '   ']],
            'lowercase status code' => [['status_code' => 'finished']],
            'non-string status code' => [['status_code' => 1]],
        ];
    }

    #[DataProvider('createHttpFailures')]
    public function test_create_container_http_failures_are_sanitized(int $status, string $expectedCode): void
    {
        $token = 'fake-create-secret-token';
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response(['error' => ['message' => 'raw '.$token]], $status),
        ]);

        $result = (new InstagramMetaPublisher)->createContainer(
            $this->connection($token),
            new InstagramPublicationPayload('Caption', 'https://cdn.example.com/image.jpg'),
        );

        $this->assertFalse($result->successful);
        $this->assertSame($expectedCode, $result->errorCode);
        $this->assertStringNotContainsString($token, (string) $result->errorMessage);
        $this->assertStringNotContainsString('raw', (string) $result->errorMessage);
        Http::assertSentCount(1);
    }

    /** @return array<string, array{int, string}> */
    public static function createHttpFailures(): array
    {
        return [
            'bad request' => [400, 'META_CREATE_FAILED'],
            'unauthorized' => [401, 'META_AUTH_FAILED'],
            'forbidden' => [403, 'META_AUTH_FAILED'],
            'server error' => [500, 'META_UNAVAILABLE'],
        ];
    }

    #[DataProvider('invalidCreateResponses')]
    public function test_create_container_invalid_success_response_fails_closed(mixed $body): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::response($body, 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new InstagramMetaPublisher)->createContainer(
            $this->connection(),
            new InstagramPublicationPayload('Caption', 'https://cdn.example.com/image.jpg'),
        );

        $this->assertFalse($result->successful);
        $this->assertSame('META_RESPONSE_INVALID', $result->errorCode);
        Http::assertSentCount(1);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidCreateResponses(): array
    {
        return [
            'malformed json' => ['{invalid-json'],
            'missing id' => [['status' => 'accepted']],
        ];
    }

    #[DataProvider('publishHttpFailures')]
    public function test_publish_container_http_failures_are_sanitized(
        int $status,
        string $expectedCode,
        bool $outcomeUncertain,
    ): void {
        $token = 'fake-publish-secret-token';
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLISH_URL => Http::response(['error' => ['message' => 'raw '.$token]], $status),
        ]);

        $result = (new InstagramMetaPublisher)->publishContainer($this->connection($token), 'container_123');

        $this->assertFalse($result->successful);
        $this->assertSame($expectedCode, $result->errorCode);
        $this->assertSame($outcomeUncertain, $result->outcomeUncertain);
        $this->assertSame('container_123', $result->externalContainerId);
        $this->assertStringNotContainsString($token, (string) $result->errorMessage);
        $this->assertStringNotContainsString('raw', (string) $result->errorMessage);
        Http::assertSentCount(1);
    }

    /** @return array<string, array{int, string, bool}> */
    public static function publishHttpFailures(): array
    {
        return [
            'bad request' => [400, 'META_PUBLISH_FAILED', false],
            'unauthorized' => [401, 'META_AUTH_FAILED', false],
            'forbidden' => [403, 'META_AUTH_FAILED', false],
            'server error' => [500, 'META_PUBLISH_OUTCOME_UNKNOWN', true],
        ];
    }

    #[DataProvider('invalidPublishResponses')]
    public function test_publish_container_invalid_success_response_is_ambiguous(mixed $body): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLISH_URL => Http::response($body, 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new InstagramMetaPublisher)->publishContainer($this->connection(), 'container_123');

        $this->assertFalse($result->successful);
        $this->assertSame('META_PUBLISH_OUTCOME_UNKNOWN', $result->errorCode);
        $this->assertTrue($result->outcomeUncertain);
        $this->assertSame('container_123', $result->externalContainerId);
        Http::assertSentCount(1);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidPublishResponses(): array
    {
        return [
            'malformed json' => ['{invalid-json'],
            'missing id' => [['status' => 'published']],
        ];
    }

    public function test_create_container_connection_failure_is_sanitized(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::CREATE_URL => Http::failedConnection('raw-create-network-secret'),
        ]);

        $result = (new InstagramMetaPublisher)->createContainer(
            $this->connection(),
            new InstagramPublicationPayload('Caption', 'https://cdn.example.com/image.jpg'),
        );

        $this->assertFalse($result->successful);
        $this->assertSame('NETWORK_ERROR', $result->errorCode);
        $this->assertStringNotContainsString('raw-create-network-secret', (string) $result->errorMessage);
        Http::assertSentCount(1);
    }

    public function test_publish_container_connection_failure_has_unknown_outcome(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLISH_URL => Http::failedConnection('raw-publish-network-secret'),
        ]);

        $result = (new InstagramMetaPublisher)->publishContainer($this->connection(), 'container_123');

        $this->assertFalse($result->successful);
        $this->assertSame('META_PUBLISH_OUTCOME_UNKNOWN', $result->errorCode);
        $this->assertTrue($result->outcomeUncertain);
        $this->assertStringNotContainsString('raw-publish-network-secret', (string) $result->errorMessage);
        Http::assertSentCount(1);
    }

    public function test_container_status_connection_failure_is_sanitized(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::STATUS_URL.'*' => Http::failedConnection('raw-status-network-secret'),
        ]);

        $result = (new InstagramMetaPublisher)->getContainerStatus($this->connection(), 'container_123');

        $this->assertFalse($result->successful);
        $this->assertSame('META_CONTAINER_STATUS_FAILED', $result->errorCode);
        $this->assertStringNotContainsString('raw-status-network-secret', (string) $result->errorMessage);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::PUBLISH_URL);
    }

    private function connection(string $token = 'fake-meta-token'): MetaConnection
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);

        return MetaConnection::create([
            'brand_id' => $brand->getKey(),
            'configured_by' => $user->getKey(),
            'instagram_account_id' => 'ig_123',
            'access_token' => $token,
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => now(),
        ]);
    }
}
