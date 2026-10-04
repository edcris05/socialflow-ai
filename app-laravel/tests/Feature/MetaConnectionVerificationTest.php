<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\ScheduledPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaConnectionVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VERIFY_URL = 'https://graph.instagram.com/v26.0/me*';

    public function test_authorized_user_verifies_matching_instagram_account(): void
    {
        $this->travelTo('2026-10-03 12:00:00');
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['user_id' => 'ig_123', 'username' => 'chatcito']),
        ]);
        [$user, $brand] = $this->brand();
        $token = 'fake-meta-verification-token';
        $connection = $this->connection($user, $brand, $token);

        $this->actingAs($user)
            ->post(route('marcas.meta.verify', $brand))
            ->assertRedirect(route('marcas.meta.show', $brand))
            ->assertSessionHas('status', 'La conexión con Instagram fue verificada.');

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_VERIFIED, $connection->status);
        $this->assertSame('2026-10-03 12:00:00', $connection->last_verified_at?->toDateTimeString());
        $this->assertNull($connection->last_error);
        $this->assertSame($token, $connection->access_token);
        Http::assertSent(function (Request $request) use ($token): bool {
            $this->assertSame('GET', $request->method());
            $this->assertStringStartsWith('https://graph.instagram.com/v26.0/me?', $request->url());
            $this->assertSame('user_id,username', $request['fields']);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer '.$token));

            return true;
        });
        Http::assertSentCount(1);
    }

    public function test_account_id_mismatch_fails_closed(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['user_id' => 'ig_other', 'username' => 'other']),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertNull($connection->last_verified_at);
        $this->assertSame('La cuenta autenticada no coincide con el Instagram Account ID configurado.', $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_invalid_token_response_is_sanitized(): void
    {
        Http::preventStrayRequests();
        $token = 'fake-rejected-token-that-must-stay-secret';
        Http::fake([
            self::VERIFY_URL => Http::response(['error' => ['message' => 'Rejected '.$token]], 401),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand, $token);

        $response = $this->actingAs($user)->followingRedirects()
            ->post(route('marcas.meta.verify', $brand));

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertNull($connection->last_verified_at);
        $this->assertSame('El token fue rechazado o no tiene permisos para consultar la cuenta de Instagram.', $connection->last_error);
        $this->assertStringNotContainsString($token, $connection->last_error);
        $response->assertOk()
            ->assertSee('ERROR DE VERIFICACIÓN')
            ->assertSee($connection->last_error)
            ->assertDontSee($token)
            ->assertDontSee('Rejected');
        Http::assertSentCount(1);
    }

    public function test_forbidden_response_is_sanitized(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['error' => ['message' => 'Raw permission details']], 403),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertSame('El token fue rechazado o no tiene permisos para consultar la cuenta de Instagram.', $connection->last_error);
        $this->assertStringNotContainsString('Raw permission details', $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_meta_oauth_error_code_190_is_treated_as_an_invalid_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response([
                'error' => [
                    'message' => 'Raw expired token details',
                    'type' => 'OAuthException',
                    'code' => 190,
                    'error_subcode' => 463,
                ],
            ], 400),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertSame('El token fue rechazado o no tiene permisos para consultar la cuenta de Instagram.', $connection->last_error);
        $this->assertStringNotContainsString('Raw expired token details', $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_meta_server_error_is_sanitized(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['error' => ['message' => 'Internal trace']], 500),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertSame('Instagram no está disponible temporalmente. Intentá verificar nuevamente más tarde.', $connection->last_error);
        $this->assertStringNotContainsString('Internal trace', $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_is_handled_without_exposing_exception(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::failedConnection('fake-meta-network-secret'),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertSame('No se pudo conectar con Instagram. Intentá verificar nuevamente.', $connection->last_error);
        $this->assertStringNotContainsString('fake-meta-network-secret', $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_malformed_json_fails_closed(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response('{invalid-json', 200, ['Content-Type' => 'application/json']),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertSame('Instagram devolvió una respuesta inválida.', $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_missing_returned_user_id_fails_closed(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['username' => 'chatcito']),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertSame('Instagram devolvió una respuesta inválida.', $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_missing_token_does_not_send_http_request(): void
    {
        Http::preventStrayRequests();
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand, null);

        $this->actingAs($user)
            ->post(route('marcas.meta.verify', $brand))
            ->assertRedirect(route('marcas.meta.show', $brand))
            ->assertSessionHas('meta_error', 'No se puede verificar: falta el access token.');

        $this->assertSame(MetaConnection::STATUS_CONFIGURED_UNVERIFIED, $connection->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_missing_instagram_account_id_does_not_send_http_request(): void
    {
        Http::preventStrayRequests();
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand, instagramAccountId: null);

        $this->actingAs($user)
            ->post(route('marcas.meta.verify', $brand))
            ->assertRedirect(route('marcas.meta.show', $brand))
            ->assertSessionHas('meta_error', 'No se puede verificar: falta el Instagram Account ID.');

        $this->assertSame(MetaConnection::STATUS_CONFIGURED_UNVERIFIED, $connection->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_cross_tenant_user_cannot_trigger_verification(): void
    {
        Http::preventStrayRequests();
        [$owner, $otherBrand] = $this->brand();
        $connection = $this->connection($owner, $otherBrand);
        [$user] = $this->brand();

        $this->actingAs($user)
            ->post(route('marcas.meta.verify', $otherBrand))
            ->assertNotFound();

        $this->assertSame(MetaConnection::STATUS_CONFIGURED_UNVERIFIED, $connection->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_guest_cannot_trigger_verification(): void
    {
        Http::preventStrayRequests();
        [$user, $brand] = $this->brand();
        $this->connection($user, $brand);

        $this->post(route('marcas.meta.verify', $brand))->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_verified_connection_can_be_verified_again(): void
    {
        $this->travelTo('2026-10-03 15:00:00');
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['user_id' => 'ig_123', 'username' => 'chatcito']),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand, attributes: [
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => '2026-10-01 10:00:00',
            'last_error' => 'Error anterior seguro.',
        ]);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_VERIFIED, $connection->status);
        $this->assertSame('2026-10-03 15:00:00', $connection->last_verified_at?->toDateTimeString());
        $this->assertNull($connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_failed_reverification_preserves_last_successful_verification_time(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['error' => ['message' => 'temporary']], 500),
        ]);
        [$user, $brand] = $this->brand();
        $connection = $this->connection($user, $brand, attributes: [
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => '2026-10-01 10:00:00',
        ]);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $connection->refresh();
        $this->assertSame(MetaConnection::STATUS_ERROR, $connection->status);
        $this->assertSame('2026-10-01 10:00:00', $connection->last_verified_at?->toDateTimeString());
        Http::assertSentCount(1);
    }

    public function test_verification_action_visibility_matches_prerequisites(): void
    {
        Http::preventStrayRequests();
        [$user, $readyBrand] = $this->brand();
        $this->connection($user, $readyBrand);
        $missingTokenBrand = Brand::factory()->create();
        $user->brands()->attach($missingTokenBrand, ['role' => 'owner']);
        $this->connection($user, $missingTokenBrand, null);
        $missingIdBrand = Brand::factory()->create();
        $user->brands()->attach($missingIdBrand, ['role' => 'owner']);
        $this->connection($user, $missingIdBrand, instagramAccountId: null);
        $this->actingAs($user);

        $this->get(route('marcas.meta.show', $readyBrand))
            ->assertSee('Verificar conexión')
            ->assertSee(route('marcas.meta.verify', $readyBrand), false);
        $this->get(route('marcas.meta.show', $missingTokenBrand))
            ->assertDontSee('Verificar conexión')
            ->assertSee('Agregá un access token para habilitar la verificación.');
        $this->get(route('marcas.meta.show', $missingIdBrand))
            ->assertDontSee('Verificar conexión')
            ->assertSee('Configurá el Instagram Account ID para habilitar la verificación.');
        Http::assertNothingSent();
    }

    public function test_verified_and_error_states_render_without_exposing_token(): void
    {
        Http::preventStrayRequests();
        [$user, $verifiedBrand] = $this->brand();
        $verifiedToken = 'fake-verified-hidden-token';
        $this->connection($user, $verifiedBrand, $verifiedToken, attributes: [
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => '2026-10-03 12:00:00',
        ]);
        $errorBrand = Brand::factory()->create();
        $user->brands()->attach($errorBrand, ['role' => 'owner']);
        $errorToken = 'fake-error-hidden-token';
        $this->connection($user, $errorBrand, $errorToken, attributes: [
            'status' => MetaConnection::STATUS_ERROR,
            'last_error' => 'Error sanitizado.',
        ]);
        $this->actingAs($user);

        $this->get(route('marcas.meta.show', $verifiedBrand))
            ->assertSee('VERIFICADO')
            ->assertSee('Última verificación exitosa')
            ->assertSee('03/10/2026 12:00')
            ->assertDontSee($verifiedToken);
        $this->get(route('marcas.meta.show', $errorBrand))
            ->assertSee('ERROR DE VERIFICACIÓN')
            ->assertSee('Error sanitizado.')
            ->assertDontSee($errorToken);
        Http::assertNothingSent();
    }

    public function test_verification_does_not_change_ready_publication_or_call_publishing_endpoint(): void
    {
        $this->travelTo('2026-10-03 12:00:00');
        Http::preventStrayRequests();
        Http::fake([
            self::VERIFY_URL => Http::response(['user_id' => 'ig_123', 'username' => 'chatcito']),
        ]);
        [$user, $brand] = $this->brand();
        $this->connection($user, $brand);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => 'Ready intacto',
            'content' => 'No publicar.',
            'status' => Draft::STATUS_APPROVED,
        ]);
        $publication = ScheduledPublication::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'scheduled_by' => $user->getKey(),
            'scheduled_for' => '2026-10-03 11:00:00',
            'status' => ScheduledPublication::STATUS_SCHEDULED,
        ]);

        $this->actingAs($user)->post(route('marcas.meta.verify', $brand))->assertRedirect();

        $publication->refresh();
        $this->assertSame(ScheduledPublication::STATUS_SCHEDULED, $publication->status);
        $this->assertSame('2026-10-03 11:00:00', $publication->scheduled_for->toDateTimeString());
        $this->assertTrue($publication->isReady());
        $this->assertSame(1, ScheduledPublication::query()->count());
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.instagram.com/v26.0/me?'));
        Http::assertSentCount(1);
    }

    /** @return array{User, Brand} */
    private function brand(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);

        return [$user, $brand];
    }

    /** @param array<string, mixed> $attributes */
    private function connection(
        User $user,
        Brand $brand,
        ?string $token = 'fake-meta-token',
        ?string $instagramAccountId = 'ig_123',
        array $attributes = [],
    ): MetaConnection {
        return MetaConnection::create([
            'brand_id' => $brand->getKey(),
            'configured_by' => $user->getKey(),
            'facebook_page_id' => null,
            'instagram_account_id' => $instagramAccountId,
            'access_token' => $token,
            'status' => MetaConnection::STATUS_CONFIGURED_UNVERIFIED,
            'last_verified_at' => null,
            'last_error' => null,
            ...$attributes,
        ]);
    }
}
