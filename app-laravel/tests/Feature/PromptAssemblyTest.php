<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Prompting\PromptComposer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PromptAssemblyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_generation_prompt_is_built_from_context_snapshot(): void
    {
        [$user, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($user, $brand, 'Promocionar stickers resistentes');
        $snapshot = $draft->contextSnapshot;

        $prompt = app(PromptComposer::class)->compose($snapshot);

        $this->assertSame($snapshot->query, $prompt->request);
        $this->assertSame($draft->id, $prompt->draftId);
        $this->assertSame($brand->id, $prompt->brandId);
        $this->assertStringContainsString('Sólo puede utilizar información proporcionada', $prompt->render());
    }

    public function test_generation_prompt_uses_historical_snapshot_not_current_knowledge_entry(): void
    {
        [$user, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($user, $brand, 'Promocionar stickers resistentes');
        $snapshot = $draft->contextSnapshot;
        $entry = KnowledgeEntry::findOrFail($snapshot->relevant_knowledge[0]['id']);

        $entry->update(['content' => 'Contenido actualizado después del snapshot']);

        $prompt = app(PromptComposer::class)->compose($snapshot->fresh());

        $this->assertStringContainsString('Producto disponible', $prompt->render());
        $this->assertStringNotContainsString('Contenido actualizado después del snapshot', $prompt->render());
    }

    public function test_prompt_includes_relevant_knowledge_and_brand_context(): void
    {
        [$user, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($user, $brand, 'Promocionar stickers resistentes');

        $prompt = app(PromptComposer::class)->compose($draft->contextSnapshot);

        $this->assertStringContainsString('Stickers resistentes', $prompt->render());
        $this->assertStringContainsString('Identidad de marca', $prompt->render());
        $this->assertStringContainsString('contexto de marca', strtolower($prompt->render()));
    }

    public function test_prompt_includes_policies_and_restrictions(): void
    {
        [$user, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($user, $brand, 'Promocionar stickers resistentes');

        $prompt = app(PromptComposer::class)->compose($draft->contextSnapshot);

        $this->assertStringContainsString('Política de precios', $prompt->render());
        $this->assertStringContainsString('Restricción', $prompt->render());
    }

    public function test_prompt_includes_warnings_and_missing_information_when_present(): void
    {
        [$user, $brand] = $this->brandContext();
        $draft = Draft::create([
            'title' => 'Draft con advertencias',
            'content' => 'Texto',
            'status' => 'draft',
            'brand_id' => $brand->id,
            'user_id' => $user->id,
        ]);

        $snapshot = new ContextSnapshot([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'query' => 'Promocionar stickers resistentes',
            'relevant_knowledge' => [],
            'brand_context' => [
                ['title' => 'Identidad de marca', 'content' => 'Marca premium'],
            ],
            'policies' => [],
            'restrictions' => [],
            'pending_knowledge' => [],
            'sources' => ['Fuente comercial'],
            'warnings' => ['Precio antes de publicación: requiere confirmación.'],
            'missing_information' => ['Stock: disponibilidad actual desconocida.'],
            'matches' => [],
        ]);

        $draft->contextSnapshot()->save($snapshot);

        $prompt = app(PromptComposer::class)->compose($snapshot);

        $this->assertStringContainsString('Precio antes de publicación: requiere confirmación.', $prompt->render());
        $this->assertStringContainsString('Stock: disponibilidad actual desconocida.', $prompt->render());
    }

    public function test_prompt_does_not_auto_include_technical_metadata_in_llm_text(): void
    {
        [$user, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($user, $brand, 'Promocionar stickers resistentes');

        $prompt = app(PromptComposer::class)->compose($draft->contextSnapshot);

        $this->assertStringNotContainsString('matched_terms', $prompt->render());
        $this->assertStringNotContainsString('score', $prompt->render());
        $this->assertArrayHasKey('matches', $prompt->metadata);
    }

    public function test_prompt_keeps_sources_and_matches_available_in_metadata(): void
    {
        [$user, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($user, $brand, 'Promocionar stickers resistentes');

        $prompt = app(PromptComposer::class)->compose($draft->contextSnapshot);

        $this->assertArrayHasKey('sources', $prompt->metadata);
        $this->assertArrayHasKey('matches', $prompt->metadata);
        $this->assertSame($draft->contextSnapshot->sources, $prompt->metadata['sources']);
    }

    public function test_user_cannot_access_prompt_preview_from_another_brand(): void
    {
        [$owner, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($owner, $brand, 'Promocionar stickers resistentes');

        $otherUser = User::factory()->create();
        $otherBrand = Brand::factory()->create();
        $otherUser->brands()->attach($otherBrand, ['role' => 'owner']);
        $this->actingAs($otherUser);

        $this->get(route('marcas.borradores.prompt.preview', [$otherBrand, $draft]))->assertNotFound();
    }

    public function test_user_cannot_access_another_users_prompt_preview_in_same_brand(): void
    {
        [$owner, $brand] = $this->brandContext();
        $draft = $this->draftWithSnapshot($owner, $brand, 'Promocionar stickers resistentes');

        $otherUser = User::factory()->create();
        $otherUser->brands()->attach($brand, ['role' => 'editor']);
        $this->actingAs($otherUser);

        $this->get(route('marcas.borradores.prompt.preview', [$brand, $draft]))->assertNotFound();
    }

    private function brandContext(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $brand->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user);

        return [$user, $brand];
    }

    private function draftWithSnapshot(User $user, Brand $brand, string $query): Draft
    {
        $draft = Draft::create([
            'title' => 'Borrador de prueba',
            'content' => 'Texto inicial',
            'status' => 'draft',
            'brand_id' => $brand->id,
            'user_id' => $user->id,
        ]);

        $entry = KnowledgeEntry::create([
            'title' => 'Stickers resistentes',
            'content' => 'Producto disponible',
            'status' => 'verified',
            'category' => 'product',
            'source' => 'Fuente producto',
            'applicability' => 'global',
            'brand_id' => $brand->id,
            'created_by' => $user->id,
        ]);

        $brandEntry = KnowledgeEntry::create([
            'title' => 'Identidad de marca',
            'content' => 'Marca premium y cercana al cliente',
            'status' => 'verified',
            'category' => 'brand_identity',
            'source' => 'Manual interno',
            'applicability' => 'global',
            'brand_id' => $brand->id,
            'created_by' => $user->id,
        ]);

        $policy = KnowledgeEntry::create([
            'title' => 'Política de precios',
            'content' => 'Consultar precios antes de publicar.',
            'status' => 'verified',
            'category' => 'policy',
            'source' => 'Fuente comercial',
            'applicability' => 'price',
            'brand_id' => $brand->id,
            'created_by' => $user->id,
        ]);

        $restriction = KnowledgeEntry::create([
            'title' => 'Restricción comercial',
            'content' => 'No inventar stock ni precios.',
            'status' => 'verified',
            'category' => 'restriction',
            'source' => 'Manual interno',
            'applicability' => 'global',
            'brand_id' => $brand->id,
            'created_by' => $user->id,
        ]);

        $snapshot = ContextSnapshot::create([
            'draft_id' => $draft->id,
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'query' => $query,
            'relevant_knowledge' => [
                $entry->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']),
            ],
            'brand_context' => [
                $brandEntry->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']),
            ],
            'policies' => [
                $policy->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']),
            ],
            'restrictions' => [
                $restriction->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']),
            ],
            'pending_knowledge' => [],
            'sources' => ['Fuente producto', 'Fuente comercial', 'Manual interno'],
            'warnings' => ['Precio antes de publicación: requiere confirmación.'],
            'missing_information' => ['Stock: disponibilidad actual desconocida.'],
            'matches' => [[
                'entry' => $entry->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']),
                'score' => 0.91,
                'matched_terms' => ['stickers', 'resistentes'],
            ]],
        ]);

        $draft->refresh();
        $draft->contextSnapshot()->save($snapshot);

        return $draft;
    }
}
