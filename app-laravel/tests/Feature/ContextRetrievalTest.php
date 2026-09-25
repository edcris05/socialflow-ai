<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Knowledge\ContextBuilder;
use App\Services\Knowledge\TextKnowledgeRetriever;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ContextRetrievalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_retriever_returns_verified_matching_entries_and_global_rules(): void
    {
        [, $brand] = $this->brandContext();
        $verified = $this->entry($brand, 'Stickers resistentes al agua', 'Producto disponible', 'verified', 'product');
        $this->entry($brand, 'Precio de stickers', 'Precio $4.500', 'verified', 'price');
        $this->entry($brand, 'Política de precios', 'Consultar precios antes de publicar.', 'verified', 'policy');
        $this->entry($brand, 'Pendiente stickers', 'Dato pendiente sobre stickers', 'pending', 'product');
        $this->entry($brand, 'Archivado', 'No usar', 'archived', 'product');

        $entries = app(TextKnowledgeRetriever::class)->retrieve($brand, 'promocionar stickers resistentes al agua');

        $this->assertTrue($entries->pluck('entry.id')->contains($verified->id));
        $this->assertTrue($entries->pluck('entry.title')->contains('Pendiente stickers'));
        $this->assertFalse($entries->pluck('entry.title')->contains('Archivado'));
    }

    public function test_retriever_ranks_title_matches_above_content_only_matches(): void
    {
        [, $brand] = $this->brandContext();
        $titleMatch = $this->entry($brand, 'Stickers resistentes', 'Producto disponible', 'verified', 'product');
        $contentMatch = $this->entry($brand, 'Catálogo comercial', 'Incluye stickers resistentes.', 'verified', 'fact');

        $matches = app(TextKnowledgeRetriever::class)->retrieve($brand, 'stickers resistentes');
        $ordered = $matches->values();

        $this->assertSame($titleMatch->id, $ordered->first()->entry->id);
        $this->assertSame($contentMatch->id, $ordered->skip(1)->first()->entry->id);
        $this->assertGreaterThan($ordered->last()->score, $ordered->first()->score);
    }

    public function test_context_package_separates_rules_sources_and_warnings(): void
    {
        [, $brand] = $this->brandContext();
        $this->entry($brand, 'Stickers resistentes al agua', 'Producto disponible', 'verified', 'product');
        $this->entry($brand, 'Precio de stickers', 'Precio $4.500', 'verified', 'price');
        $this->entry($brand, 'Política de precios', 'Consultar precios antes de publicar.', 'verified', 'policy', 'Fuente comercial');
        $this->entry($brand, 'Restricción comercial', 'No inventar datos.', 'verified', 'restriction');
        $this->entry($brand, 'Stock disponible', 'Las cantidades no se encuentran documentadas.', 'verified', 'fact');

        $package = app(ContextBuilder::class)->build($brand, 'Promocionar stickers resistentes al agua');

        $this->assertTrue($package->relevantKnowledge->contains('title', 'Stickers resistentes al agua'));
        $this->assertTrue($package->policies->contains('title', 'Política de precios'));
        $this->assertTrue($package->restrictions->contains('title', 'Restricción comercial'));
        $this->assertContains('Precio antes de publicación: requiere confirmación.', $package->warnings);
        $this->assertContains('Stock actual: requiere confirmación.', $package->warnings);
        $this->assertContains('Fuente comercial', $package->sources->all());
    }

    public function test_context_package_deduplicates_warnings(): void
    {
        [, $brand] = $this->brandContext();
        $this->entry($brand, 'Pendiente stickers', 'Dato pendiente.', 'pending', 'product');
        $this->entry($brand, 'Pendiente stickers', 'Otro dato pendiente.', 'pending', 'product');

        $package = app(ContextBuilder::class)->build($brand, 'promocionar stickers');

        $this->assertSame(count($package->warnings), count(array_unique($package->warnings)));
        $this->assertSame(1, count(array_filter($package->warnings, fn (string $warning): bool => $warning === 'Información pendiente: Pendiente stickers.')));
    }

    public function test_brand_context_is_separate_from_relevant_knowledge(): void
    {
        [, $brand] = $this->brandContext();
        $this->entry($brand, 'Stickers resistentes', 'Producto disponible.', 'verified', 'product');
        $identity = $this->entry($brand, 'Identidad y tono de marca', 'Voz cercana y clara.', 'verified', 'brand_identity');

        $package = app(ContextBuilder::class)->build($brand, 'promocionar stickers');

        $this->assertTrue($package->brandContext->contains('id', $identity->id));
        $this->assertFalse($package->relevantKnowledge->contains('id', $identity->id));
    }

    public function test_context_preview_renders_escaped_line_breaks_for_relevant_and_brand_context(): void
    {
        [, $brand] = $this->brandContext();
        $this->entry($brand, 'Stickers resistentes', 'Primera línea\\nSegunda línea.', 'verified', 'product');
        $this->entry($brand, 'Identidad de marca', 'Tono cercano\\nMensaje claro.', 'verified', 'brand_identity');

        $this->post(route('marcas.contexto.preview', $brand), ['query' => 'promocionar stickers'])
            ->assertOk()
            ->assertSee("Primera línea\nSegunda línea.", false)
            ->assertSee("Tono cercano\nMensaje claro.", false)
            ->assertDontSee('Primera línea\\nSegunda línea.', false)
            ->assertDontSee('Tono cercano\\nMensaje claro.', false);
    }

    public function test_local_context_preview_exposes_ranking_debug_without_changing_functional_context(): void
    {
        [, $brand] = $this->brandContext();
        $entry = $this->entry($brand, 'Stickers resistentes', 'Producto disponible.', 'verified', 'product');

        $package = app(ContextBuilder::class)->build($brand, 'promocionar stickers');
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->assertTrue($this->app->environment('local'));
        $html = view('context.create', [
            'brand' => $brand,
            'package' => $package,
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertTrue($package->relevantKnowledge->contains('id', $entry->id));
        $this->assertStringContainsString('Depuración del ranking', $html);
        $this->assertStringContainsString('score', $html);
        $this->assertStringContainsString('términos:', $html);
        $this->assertStringContainsString($entry->title, $html);
    }

    public function test_future_ideas_and_unrelated_policies_do_not_contaminate_context(): void
    {
        [, $brand] = $this->brandContext();
        $this->entry($brand, 'Stickers resistentes al agua', 'Producto disponible', 'verified', 'product');
        $this->entry($brand, 'Precio de stickers', 'Precio $4.500', 'verified', 'price');
        $this->entry($brand, 'Idea futura - Cuadernillo', 'Idea universitaria futura', 'verified', 'future_idea');
        $this->entry($brand, 'Política de reclamos', 'Evaluar cada reclamo.', 'verified', 'policy', null, 'customer_service');
        $this->entry($brand, 'Política de precios', 'Consultar precios antes de publicar.', 'verified', 'policy', null, 'price');

        $package = app(ContextBuilder::class)->build($brand, 'Promocionar stickers resistentes al agua');

        $this->assertFalse($package->relevantKnowledge->contains('title', 'Idea futura - Cuadernillo'));
        $this->assertFalse($package->policies->contains('title', 'Política de reclamos'));
        $this->assertTrue($package->policies->contains('title', 'Política de precios'));
    }

    public function test_location_query_includes_location_rules_but_not_customer_service_rules(): void
    {
        [, $brand] = $this->brandContext();
        $this->entry($brand, 'Ubicación pública', 'Mencionar solo la zona.', 'verified', 'policy', null, 'location');
        $this->entry($brand, 'Cambios y reclamos', 'Evaluar cada caso.', 'verified', 'policy', null, 'customer_service');

        $package = app(ContextBuilder::class)->build($brand, '¿Dónde se puede retirar el pedido?');

        $this->assertTrue($package->policies->contains('title', 'Ubicación pública'));
        $this->assertFalse($package->policies->contains('title', 'Cambios y reclamos'));
    }

    public function test_context_preview_is_protected_and_isolated_by_brand(): void
    {
        [$user, $brand] = $this->brandContext();
        $otherBrand = Brand::factory()->create();
        $user->brands()->attach($otherBrand, ['role' => 'owner']);
        $this->entry($otherBrand, 'Secreto', 'No pertenece a la marca consultada', 'verified', 'fact');

        $this->get(route('marcas.contexto.create', $brand))->assertOk();
        $this->post(route('marcas.contexto.preview', $brand), ['query' => 'Secreto'])
            ->assertOk()
            ->assertDontSee('No pertenece a la marca consultada');
        $this->post(route('marcas.contexto.preview', $brand), ['query' => ''])->assertSessionHasErrors('query');
    }

    private function brandContext(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $this->actingAs($user);

        return [$user, $brand];
    }

    private function entry(Brand $brand, string $title, string $content, string $status, string $category, ?string $source = null, string $applicability = 'global'): KnowledgeEntry
    {
        $entry = new KnowledgeEntry([
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'category' => $category,
            'source' => $source,
            'applicability' => $applicability,
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();
        $entry->save();

        return $entry;
    }
}