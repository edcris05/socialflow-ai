<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KnowledgeManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_create_verify_and_search_brand_knowledge(): void
    {
        [$user, $brand] = $this->brandContext();

        $response = $this->post(route('marcas.conocimiento.store', $brand), [
            'title' => 'Política de cambios',
            'content' => 'Los cambios se aceptan dentro de treinta días.',
            'source' => 'Manual interno',
            'status' => 'pending',
        ]);

        $entry = KnowledgeEntry::query()->firstOrFail();
        $response->assertRedirect(route('marcas.conocimiento.show', [$brand, $entry]));
        $this->assertDatabaseHas('knowledge_audits', [
            'knowledge_entry_id' => $entry->id,
            'user_id' => $user->id,
            'action' => 'created',
        ]);

        $this->patch(route('marcas.conocimiento.verify', [$brand, $entry]))
            ->assertRedirect();
        $this->assertDatabaseHas('knowledge_entries', [
            'id' => $entry->id,
            'status' => 'verified',
            'verified_by' => $user->id,
        ]);
        $this->assertDatabaseHas('knowledge_audits', [
            'knowledge_entry_id' => $entry->id,
            'action' => 'verified',
        ]);

        $this->get(route('marcas.conocimiento.index', [$brand, 'q' => 'treinta']))
            ->assertOk()
            ->assertSee($entry->title)
            ->assertSee('treinta');
    }

    public function test_user_cannot_read_knowledge_from_another_brand(): void
    {
        [, $brand] = $this->brandContext();
        $otherBrand = Brand::factory()->create();
        $entry = new KnowledgeEntry([
            'title' => 'Privado',
            'content' => 'Contenido privado',
            'status' => 'pending',
        ]);
        $entry->brand()->associate($otherBrand);
        $entry->created_by = User::factory()->create()->id;
        $entry->save();

        $this->get(route('marcas.conocimiento.show', [$brand, $entry]))->assertNotFound();
        $this->get(route('marcas.conocimiento.index', $brand))
            ->assertOk()
            ->assertDontSee('Privado');
    }

    public function test_user_can_create_and_update_a_brand_draft(): void
    {
        [, $brand] = $this->brandContext();

        $this->post(route('marcas.borradores.store', $brand), [
            'title' => 'Post de lanzamiento',
            'content' => 'Texto inicial',
            'status' => 'draft',
        ])->assertRedirect(route('marcas.borradores.index', $brand));

        $this->assertDatabaseHas('drafts', [
            'brand_id' => $brand->id,
            'title' => 'Post de lanzamiento',
        ]);
    }

    /** @return array{0: User, 1: Brand} */
    private function brandContext(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $this->actingAs($user);

        return [$user, $brand];
    }
}