<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandAutopublishingSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BrandAutopublishingSettingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_missing_setting_and_database_default_are_disabled(): void
    {
        $brandWithoutSetting = Brand::factory()->create();
        $brandWithDefaultSetting = Brand::factory()->create();
        $setting = BrandAutopublishingSetting::create([
            'brand_id' => $brandWithDefaultSetting->getKey(),
        ]);
        $incompleteBrand = Brand::factory()->create();
        BrandAutopublishingSetting::factory()->for($incompleteBrand)->create([
            'enabled' => true,
            'enabled_by' => null,
            'enabled_at' => null,
        ]);

        $this->assertFalse($brandWithoutSetting->autopublishingEnabled());
        $this->assertFalse($setting->fresh()->enabled);
        $this->assertFalse($brandWithDefaultSetting->fresh()->autopublishingEnabled());
        $this->assertFalse($incompleteBrand->autopublishingEnabled());
    }

    public function test_owner_activates_and_deactivates_with_auditable_metadata(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $this->travelTo('2026-10-09 10:00:00');

        $this->actingAs($user)
            ->post(route('marcas.autopublicacion.store', $brand), ['confirm_enable' => '1'])
            ->assertRedirect(route('marcas.show', $brand))
            ->assertSessionHas('status', 'La autopublicación fue activada para esta marca.');

        $setting = BrandAutopublishingSetting::query()->sole();
        $this->assertTrue($setting->enabled);
        $this->assertSame($user->getKey(), $setting->enabled_by);
        $this->assertSame('2026-10-09 10:00:00', $setting->enabled_at?->toDateTimeString());
        $this->assertNull($setting->disabled_by);
        $this->assertNull($setting->disabled_at);

        $this->travelTo('2026-10-09 11:00:00');

        $this->delete(route('marcas.autopublicacion.destroy', $brand))
            ->assertRedirect(route('marcas.show', $brand))
            ->assertSessionHas('status', 'La autopublicación fue desactivada para esta marca.');

        $setting->refresh();
        $this->assertFalse($setting->enabled);
        $this->assertSame($user->getKey(), $setting->enabled_by);
        $this->assertSame('2026-10-09 10:00:00', $setting->enabled_at?->toDateTimeString());
        $this->assertSame($user->getKey(), $setting->disabled_by);
        $this->assertSame('2026-10-09 11:00:00', $setting->disabled_at?->toDateTimeString());
    }

    public function test_repeated_transitions_are_idempotent_and_preserve_original_audit_metadata(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $brand = Brand::factory()->create();
        $firstOwner->brands()->attach($brand, ['role' => 'owner']);
        $secondOwner->brands()->attach($brand, ['role' => 'owner']);
        $this->travelTo('2026-10-09 10:00:00');

        $this->actingAs($firstOwner)
            ->post(route('marcas.autopublicacion.store', $brand), ['confirm_enable' => '1'])
            ->assertRedirect(route('marcas.show', $brand));
        $this->travelTo('2026-10-09 11:00:00');
        $this->actingAs($secondOwner)
            ->post(route('marcas.autopublicacion.store', $brand), ['confirm_enable' => '1'])
            ->assertRedirect(route('marcas.show', $brand));

        $setting = BrandAutopublishingSetting::query()->sole();
        $this->assertSame($firstOwner->getKey(), $setting->enabled_by);
        $this->assertSame('2026-10-09 10:00:00', $setting->enabled_at?->toDateTimeString());

        $this->travelTo('2026-10-09 12:00:00');
        $this->actingAs($firstOwner)
            ->delete(route('marcas.autopublicacion.destroy', $brand))
            ->assertRedirect(route('marcas.show', $brand));
        $this->travelTo('2026-10-09 13:00:00');
        $this->actingAs($secondOwner)
            ->delete(route('marcas.autopublicacion.destroy', $brand))
            ->assertRedirect(route('marcas.show', $brand));

        $setting->refresh();
        $this->assertFalse($setting->enabled);
        $this->assertSame($firstOwner->getKey(), $setting->disabled_by);
        $this->assertSame('2026-10-09 12:00:00', $setting->disabled_at?->toDateTimeString());
        $this->assertDatabaseCount('brand_autopublishing_settings', 1);
    }

    public function test_deactivating_missing_setting_is_an_idempotent_no_op(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);

        $this->actingAs($user)
            ->delete(route('marcas.autopublicacion.destroy', $brand))
            ->assertRedirect(route('marcas.show', $brand));

        $this->assertDatabaseCount('brand_autopublishing_settings', 0);
        $this->assertFalse($brand->fresh()->autopublishingEnabled());
    }

    public function test_deleting_audit_actor_nulls_foreign_keys_but_preserves_timestamps(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $setting = BrandAutopublishingSetting::factory()->for($brand)->create([
            'enabled' => false,
            'enabled_by' => $user->getKey(),
            'enabled_at' => '2026-10-09 10:00:00',
            'disabled_by' => $user->getKey(),
            'disabled_at' => '2026-10-09 11:00:00',
        ]);

        $user->delete();

        $setting->refresh();
        $this->assertNull($setting->enabled_by);
        $this->assertNull($setting->disabled_by);
        $this->assertSame('2026-10-09 10:00:00', $setting->enabled_at?->toDateTimeString());
        $this->assertSame('2026-10-09 11:00:00', $setting->disabled_at?->toDateTimeString());
    }

    public function test_database_rejects_duplicate_setting_for_same_brand(): void
    {
        $brand = Brand::factory()->create();
        BrandAutopublishingSetting::factory()->for($brand)->create();

        $this->expectException(QueryException::class);

        BrandAutopublishingSetting::factory()->for($brand)->create();
    }

    public function test_deleting_brand_cascades_autopublishing_setting(): void
    {
        $brand = Brand::factory()->create();
        BrandAutopublishingSetting::factory()->for($brand)->create();

        $brand->delete();

        $this->assertDatabaseCount('brand_autopublishing_settings', 0);
    }

    public function test_activation_requires_explicit_confirmation(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);

        $this->actingAs($user)
            ->post(route('marcas.autopublicacion.store', $brand))
            ->assertSessionHasErrors('confirm_enable');

        $this->assertDatabaseCount('brand_autopublishing_settings', 0);
    }

    public function test_cross_tenant_user_cannot_change_setting(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $brand = Brand::factory()->create();
        $owner->brands()->attach($brand, ['role' => 'owner']);
        BrandAutopublishingSetting::factory()->enabled()->for($brand)->create([
            'enabled_by' => $owner->getKey(),
        ]);

        $this->actingAs($outsider)
            ->post(route('marcas.autopublicacion.store', $brand), ['confirm_enable' => '1'])
            ->assertNotFound();
        $this->delete(route('marcas.autopublicacion.destroy', $brand))->assertNotFound();

        $setting = BrandAutopublishingSetting::query()->sole();
        $this->assertTrue($setting->enabled);
        $this->assertSame($owner->getKey(), $setting->enabled_by);
        $this->assertNull($setting->disabled_by);
    }

    public function test_brand_page_shows_fail_closed_state_and_activation_confirmation(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);

        $this->actingAs($user)
            ->get(route('marcas.show', $brand))
            ->assertOk()
            ->assertSee('Autopublicación')
            ->assertSee('DESACTIVADA')
            ->assertSee('Confirmo que quiero habilitar la autopublicación para esta marca.')
            ->assertSee('¿Confirmas la activación de la autopublicación para esta marca?')
            ->assertSee('Esta política por marca no activa los controles globales ni configura un scheduler en el host.');
    }
}
