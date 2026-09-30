<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use App\Models\User;
use Database\Seeders\ArtMadeCommercialKnowledgeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
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

    public function test_grounding_metadata_accepts_confirmed_claims_and_open_empty_allowed_uses(): void
    {
        [, $brand] = $this->brandContext();
        $metadata = $this->groundingMetadata();
        $entry = new KnowledgeEntry([
            'title' => 'Stickers resistentes al agua',
            'content' => 'Adhesivo resistente al agua.',
            'status' => 'verified',
            'grounding_metadata' => $metadata,
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();
        $entry->save();

        $this->assertSame($metadata, $entry->fresh()->grounding_metadata);
        $this->assertSame([], $entry->grounding_metadata['allowed_uses']['values']);
        $this->assertSame('open', $entry->grounding_metadata['allowed_uses']['coverage']);
        $this->assertNotContains('impermeable', $entry->grounding_metadata['claims'][0]['phrases']);

        $legacy = new KnowledgeEntry(['title' => 'Legacy', 'content' => 'Texto anterior.', 'status' => 'verified']);
        $legacy->brand()->associate($brand);
        $legacy->created_by = auth()->id();
        $legacy->save();
        $this->assertNull($legacy->fresh()->grounding_metadata);
    }

    public function test_grounding_metadata_rejects_an_unknown_coverage_value(): void
    {
        $metadata = $this->groundingMetadata();
        $metadata['allowed_uses']['coverage'] = 'partial';

        $this->expectException(InvalidArgumentException::class);
        new KnowledgeEntry([
            'title' => 'Metadata inválida',
            'content' => 'Contenido',
            'status' => 'verified',
            'grounding_metadata' => $metadata,
        ]);
    }

    public function test_grounding_metadata_rejects_evidence_that_is_not_present_in_content(): void
    {
        [, $brand] = $this->brandContext();
        $entry = new KnowledgeEntry([
            'title' => 'Stickers sin evidencia',
            'content' => 'Stickers adhesivos personalizados.',
            'status' => 'verified',
            'grounding_metadata' => $this->groundingMetadata(),
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();

        $this->expectException(InvalidArgumentException::class);
        $entry->save();
    }

    public function test_grounding_evidence_accepts_equivalent_case_and_whitespace(): void
    {
        [, $brand] = $this->brandContext();
        $metadata = $this->groundingMetadata();
        $metadata['claims'][0]['evidence_excerpt'] = "adhesivo\nresistente   al agua";
        $entry = new KnowledgeEntry([
            'title' => 'Stickers con evidencia normalizada',
            'content' => "ADHESIVO resistente\r\nal agua.",
            'status' => 'verified',
            'grounding_metadata' => $metadata,
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();
        $entry->save();

        $this->assertSame($metadata, $entry->fresh()->grounding_metadata);
    }

    public function test_http_update_cannot_leave_existing_grounding_evidence_stale(): void
    {
        [, $brand] = $this->brandContext();
        $entry = new KnowledgeEntry([
            'title' => 'Stickers resistentes',
            'content' => 'Adhesivo resistente al agua.',
            'source' => 'Fuente confirmada',
            'category' => 'product',
            'applicability' => 'global',
            'status' => 'verified',
            'grounding_metadata' => $this->groundingMetadata(),
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();
        $entry->save();

        $this->put(route('marcas.conocimiento.update', [$brand, $entry]), [
            'title' => $entry->title,
            'content' => 'Stickers adhesivos personalizados.',
            'source' => $entry->source,
            'category' => $entry->category,
            'applicability' => $entry->applicability,
            'status' => $entry->status,
        ])->assertSessionHasErrors('content');

        $this->assertSame('Adhesivo resistente al agua.', $entry->fresh()->content);
        $this->assertDatabaseCount('knowledge_audits', 0);
    }

    public function test_direct_model_update_cannot_leave_existing_grounding_evidence_stale(): void
    {
        [, $brand] = $this->brandContext();
        $entry = new KnowledgeEntry([
            'title' => 'Stickers resistentes',
            'content' => 'Adhesivo resistente al agua.',
            'status' => 'verified',
            'grounding_metadata' => $this->groundingMetadata(),
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();
        $entry->save();
        $entry->content = 'Stickers adhesivos personalizados.';
        $rejected = false;

        try {
            $entry->save();
        } catch (InvalidArgumentException) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Expected stale grounding evidence to reject a direct model save.');
        $this->assertSame('Adhesivo resistente al agua.', $entry->fresh()->content);
        $this->assertDatabaseCount('knowledge_audits', 0);
    }

    public function test_internal_update_can_change_content_and_metadata_coherently(): void
    {
        [, $brand] = $this->brandContext();
        $entry = new KnowledgeEntry([
            'title' => 'Stickers resistentes',
            'content' => 'Adhesivo resistente al agua.',
            'status' => 'verified',
            'grounding_metadata' => $this->groundingMetadata(),
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = auth()->id();
        $entry->save();
        $updatedMetadata = [
            'subject' => 'stickers',
            'claims' => [[
                'predicate' => 'finish',
                'value' => 'matte',
                'phrases' => ['acabado mate'],
                'evidence_excerpt' => 'Acabado mate',
            ]],
            'allowed_uses' => ['values' => [], 'coverage' => 'open'],
        ];

        $entry->fill([
            'content' => 'Acabado mate confirmado.',
            'grounding_metadata' => $updatedMetadata,
        ])->save();

        $this->assertSame('Acabado mate confirmado.', $entry->fresh()->content);
        $this->assertSame($updatedMetadata, $entry->grounding_metadata);
    }

    public function test_art_made_sticker_metadata_and_its_changes_are_auditable(): void
    {
        [$user, $brand] = $this->brandContext();
        $brand->update(['slug' => 'art-made-to-print']);
        $this->seed(ArtMadeCommercialKnowledgeSeeder::class);
        $entry = KnowledgeEntry::query()->where('brand_id', $brand->id)->where('title', 'Precios de stickers')->sole();
        $metadata = $this->groundingMetadata();

        $this->assertSame($metadata, $entry->grounding_metadata);
        $this->assertSame($metadata, $entry->audits()->latest()->firstOrFail()->after['grounding_metadata']);

        $entry->update([
            'content' => 'Contenido comercial anterior.',
            'grounding_metadata' => null,
        ]);
        $this->seed(ArtMadeCommercialKnowledgeSeeder::class);
        $audit = $entry->audits()->orderByDesc('id')->firstOrFail();

        $this->assertSame('updated', $audit->action);
        $this->assertSame('Contenido comercial anterior.', $audit->before['content']);
        $this->assertNull($audit->before['grounding_metadata']);
        $this->assertStringContainsString('Adhesivo resistente al agua', $audit->after['content']);
        $this->assertSame($metadata, $audit->after['grounding_metadata']);
        $this->assertSame($user->id, $audit->user_id);
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

    /** @return array<string, mixed> */
    private function groundingMetadata(): array
    {
        return [
            'subject' => 'stickers',
            'claims' => [[
                'predicate' => 'water_resistance',
                'value' => 'resistant',
                'phrases' => ['resistentes al agua'],
                'evidence_excerpt' => 'Adhesivo resistente al agua',
            ]],
            'allowed_uses' => ['values' => [], 'coverage' => 'open'],
        ];
    }
}
