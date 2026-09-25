<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Knowledge\ContextBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ContextSnapshotTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_a_draft_with_a_historical_context_snapshot_without_a_second_retrieval(): void
    {
        [$user,$brand] = $this->brandContext();
        $entry = $this->entry($brand, 'Stickers resistentes', 'Producto disponible', 'product', 'Fuente producto');
        $this->entry($brand, 'Precio de stickers', 'Precio \$4.500', 'price');
        $this->entry($brand, 'Politica de precios', 'Consultar precios antes de publicar.', 'policy', 'Fuente comercial');
        $response = $this->post(route('marcas.contexto.preview', $brand), ['query' => 'Promocionar stickers resistentes'])->assertOk()->assertSee('Crear borrador con este contexto');
        preg_match('/name="context_token" value="([^"]+)"/', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->post(route('marcas.contexto.borradores.store', $brand), ['context_token' => $matches[1]])->assertRedirect();
        $draft = Draft::sole();
        $snapshot = $draft->contextSnapshot;
        $this->assertSame($brand->id, $draft->brand_id);
        $this->assertSame($user->id, $draft->user_id);
        $this->assertSame('Promocionar stickers resistentes', $snapshot->query);
        $this->assertContains('Fuente producto', $snapshot->sources);
        $this->assertContains('Fuente comercial', $snapshot->sources);
        $this->assertContains("Precio antes de publicaci\u{00F3}n: requiere confirmaci\u{00F3}n.", $snapshot->warnings);
        $this->assertContains('Stock: disponibilidad actual desconocida.', $snapshot->missing_information);
        $entry->update(['content' => 'Contenido cambiado despues del borrador.']);
        $this->assertSame('Producto disponible', $snapshot->fresh()->relevant_knowledge[0]['content']);
    }

    public function test_accepts_a_json_round_tripped_session_payload(): void
    {
        [$user,$brand] = $this->brandContext();
        $this->entry($brand, 'Stickers resistentes', 'Producto disponible', 'product', 'Fuente producto');
        $token = str_repeat('a', 40);
        $package = app(ContextBuilder::class)->build($brand, 'Promocionar stickers resistentes');
        $prepared = json_decode(json_encode(['brand_id' => (string) $brand->getKey(), 'user_id' => (string) $user->getKey(), 'data' => $package->toSessionData()], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->withSession(['prepared-contexts' => [$token => $prepared]])->post(route('marcas.contexto.borradores.store', $brand), ['context_token' => $token])->assertRedirect();
        $this->assertDatabaseCount('drafts', 1);
    }

    public function test_user_cannot_access_a_snapshot_from_another_brand(): void
    {
        [$owner,$brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($owner, $brand);
        $otherUser = User::factory()->create();
        $otherBrand = Brand::factory()->create();
        $otherUser->brands()->attach($otherBrand, ['role' => 'owner']);
        $this->actingAs($otherUser)->get(route('marcas.borradores.edit', [$otherBrand, $draft]))->assertNotFound();
    }

    public function test_user_cannot_access_another_users_snapshot_in_the_same_brand(): void
    {
        [$owner,$brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($owner, $brand);
        $otherUser = User::factory()->create();
        $otherUser->brands()->attach($brand, ['role' => 'editor']);
        $this->actingAs($otherUser)->get(route('marcas.borradores.edit', [$brand, $draft]))->assertNotFound();
    }

    private function brandContext(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $this->actingAs($user);

        return [$user, $brand];
    }

    private function entry(Brand $brand, string $title, string $content, string $category, ?string $source = null): KnowledgeEntry
    {
        $entry = new KnowledgeEntry(['title' => $title, 'content' => $content, 'status' => 'verified', 'category' => $category, 'source' => $source, 'applicability' => 'global']);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();
        $entry->save();

        return $entry;
    }

    private function draftWithSnapshot(User $user, Brand $brand): Draft
    {
        $draft = new Draft(['title' => 'Draft', 'content' => '', 'status' => 'draft']);
        $draft->brand()->associate($brand);
        $draft->user()->associate($user);
        $draft->save();
        ContextSnapshot::create(['draft_id' => $draft->id, 'brand_id' => $brand->id, 'user_id' => $user->id, 'query' => 'Query', 'relevant_knowledge' => [], 'brand_context' => [], 'policies' => [], 'restrictions' => [], 'pending_knowledge' => [], 'sources' => ['Private source'], 'warnings' => [], 'missing_information' => [], 'matches' => []]);

        return $draft;
    }
}
