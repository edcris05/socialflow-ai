<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_lists_existing_brands(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Marca Norte',
            'slug' => 'marca-norte',
        ]);
        $this->user->brands()->attach($brand);

        $response = $this->get(route('marcas.index'));

        $response->assertOk();
        $response->assertSee($brand->name);
        $response->assertSee($brand->slug);
    }

    public function test_creates_a_brand_with_verified_context_fields(): void
    {
        $payload = [
            'name' => 'Marca Sur',
            'slug' => 'marca-sur',
            'description' => 'Información inicial confirmada para la marca.',
            'tone_of_voice' => 'Claro y cercano',
            'target_audience' => 'Personas interesadas en la demostración',
            'social_channels_text' => "Instagram\nLinkedIn",
            'restrictions_text' => "No inventar condiciones.\nRevisar antes de publicar.",
        ];

        $response = $this->post(route('marcas.store'), $payload);

        $brand = Brand::query()->firstOrFail();

        $response->assertRedirect(route('marcas.show', $brand));
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Marca Sur',
            'slug' => 'marca-sur',
            'tone_of_voice' => 'Claro y cercano',
        ]);
        $this->assertSame(['Instagram', 'LinkedIn'], $brand->social_channels);
        $this->assertSame(['No inventar condiciones.', 'Revisar antes de publicar.'], $brand->restrictions);
    }

    public function test_rejects_a_duplicated_slug_with_a_clear_message(): void
    {
        Brand::factory()->create(['slug' => 'marca-existente']);

        $response = $this->from(route('marcas.create'))->post(route('marcas.store'), [
            'name' => 'Otra marca',
            'slug' => 'marca-existente',
        ]);

        $response->assertRedirect(route('marcas.create'));
        $response->assertSessionHasErrors([
            'slug' => 'Ya existe una marca con este slug.',
        ]);
        $this->assertDatabaseCount('brands', 1);
    }

    public function test_updates_a_brand(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Marca Original',
            'slug' => 'marca-original',
        ]);
        $this->user->brands()->attach($brand);

        $response = $this->put(route('marcas.update', $brand), [
            'name' => 'Marca Actualizada',
            'slug' => 'marca-actualizada',
            'description' => 'Descripción revisada.',
            'tone_of_voice' => 'Profesional',
            'target_audience' => 'Equipo de demostración',
            'social_channels_text' => "Instagram\nFacebook",
            'restrictions_text' => 'No publicar sin revisión.',
        ]);

        $response->assertRedirect(route('marcas.show', $brand));
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Marca Actualizada',
            'slug' => 'marca-actualizada',
            'tone_of_voice' => 'Profesional',
        ]);

        $brand->refresh();

        $this->assertSame(['Instagram', 'Facebook'], $brand->social_channels);
        $this->assertSame(['No publicar sin revisión.'], $brand->restrictions);
    }

    public function test_does_not_expose_another_users_brand(): void
    {
        $otherUser = User::factory()->create();
        $brand = Brand::factory()->create();
        $otherUser->brands()->attach($brand);

        $this->get(route('marcas.show', $brand))->assertNotFound();
        $this->put(route('marcas.update', $brand), [
            'name' => 'Intento de acceso',
            'slug' => 'intento-de-acceso',
        ])->assertNotFound();
    }
}
