<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\MetaConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaConnectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_authorized_user_configures_unverified_meta_connection_with_encrypted_token(): void
    {
        [$user, $brand] = $this->brand();
        $token = 'fake-meta-token-'.str_repeat('x', 20);

        $this->actingAs($user)
            ->post(route('marcas.meta.store', $brand), [
                'facebook_page_id' => 'page_123',
                'instagram_account_id' => 'ig_456',
                'access_token' => $token,
            ])
            ->assertRedirect(route('marcas.meta.show', $brand));

        $connection = MetaConnection::query()->sole();
        $this->assertSame(MetaConnection::STATUS_CONFIGURED_UNVERIFIED, $connection->status);
        $this->assertSame('page_123', $connection->facebook_page_id);
        $this->assertSame('ig_456', $connection->instagram_account_id);
        $this->assertSame($token, $connection->access_token);
        $rawToken = DB::table('meta_connections')->where('id', $connection->getKey())->value('access_token');
        $this->assertIsString($rawToken);
        $this->assertNotSame($token, $rawToken);
        $this->assertNotNull($connection->configured_by);

        $this->get(route('marcas.meta.show', $brand))
            ->assertOk()
            ->assertSee('CONFIGURADO — SIN VERIFICAR')
            ->assertSee('Token configurado: Sí')
            ->assertDontSee($token)
            ->assertDontSee('fake-meta-token');
        Http::assertNothingSent();
    }

    public function test_empty_token_update_preserves_existing_token_and_explicit_replacement_works(): void
    {
        [$user, $brand] = $this->brand();
        $original = 'fake-original-token';
        $replacement = 'fake-replacement-token';
        $this->configure($user, $brand, $original);

        $this->actingAs($user)->post(route('marcas.meta.store', $brand), [
            'facebook_page_id' => 'page_updated',
            'instagram_account_id' => 'ig_updated',
            'access_token' => '',
        ])->assertRedirect();
        $this->assertSame($original, MetaConnection::query()->sole()->access_token);
        $this->actingAs($user)->get(route('marcas.meta.show', $brand))
            ->assertSee('El token existente se conservó.');

        $this->post(route('marcas.meta.store', $brand), [
            'facebook_page_id' => 'page_updated_again',
            'instagram_account_id' => 'ig_updated_again',
            'access_token' => $replacement,
        ])->assertRedirect();
        $this->assertSame($replacement, MetaConnection::query()->sole()->access_token);
        $this->actingAs($user)->get(route('marcas.meta.show', $brand))
            ->assertSee('El token fue actualizado.');
        $this->assertSame(1, MetaConnection::query()->count());
    }

    public function test_cross_tenant_user_cannot_view_modify_replace_or_disconnect_connection(): void
    {
        [$owner, $otherBrand] = $this->brand();
        $this->configure($owner, $otherBrand, 'fake-tenant-token');
        $otherConnection = MetaConnection::query()->sole();
        [$user, $brand] = $this->brand();
        $this->actingAs($user);

        $this->get(route('marcas.meta.show', $otherBrand))->assertNotFound();
        $this->post(route('marcas.meta.store', $otherBrand), ['access_token' => 'fake-attack-token'])->assertNotFound();
        $this->delete(route('marcas.meta.token.destroy', $otherBrand))->assertNotFound();

        $this->assertSame('fake-tenant-token', $otherConnection->fresh()->access_token);
        $this->assertFalse($brand->metaConnection()->exists());
    }

    public function test_explicit_token_deletion_preserves_connection_and_identifiers(): void
    {
        [$user, $brand] = $this->brand();
        $this->configure($user, $brand, 'fake-disconnect-token');

        $this->actingAs($user)
            ->delete(route('marcas.meta.token.destroy', $brand))
            ->assertRedirect(route('marcas.meta.show', $brand));

        $connection = MetaConnection::query()->sole();
        $this->assertNull($connection->access_token);
        $this->assertSame('page_123', $connection->facebook_page_id);
        $this->assertSame('ig_456', $connection->instagram_account_id);
        $this->assertSame(MetaConnection::STATUS_CONFIGURED_UNVERIFIED, $connection->status);
        $this->assertNull($connection->last_verified_at);
        $this->actingAs($user)->get(route('marcas.meta.show', $brand))
            ->assertSee('Token configurado: No')
            ->assertDontSee('fake-disconnect-token');
        $this->assertDatabaseHas('meta_connections', ['brand_id' => $brand->getKey()]);
        $this->assertDatabaseHas('brands', ['id' => $brand->getKey()]);
    }

    public function test_manual_connection_cannot_be_verified_without_external_verification(): void
    {
        [$user, $brand] = $this->brand();
        $this->configure($user, $brand, 'fake-unverified-token');

        $this->assertSame(MetaConnection::STATUS_CONFIGURED_UNVERIFIED, MetaConnection::query()->sole()->status);
        $this->assertNull(MetaConnection::query()->sole()->last_verified_at);
        Http::assertNothingSent();
    }

    private function configure(User $user, Brand $brand, string $token): MetaConnection
    {
        $this->actingAs($user)
            ->post(route('marcas.meta.store', $brand), [
                'facebook_page_id' => 'page_123',
                'instagram_account_id' => 'ig_456',
                'access_token' => $token,
            ])
            ->assertRedirect();

        return MetaConnection::query()->latest('id')->firstOrFail();
    }

    /** @return array{User, Brand} */
    private function brand(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);

        return [$user, $brand];
    }
}
