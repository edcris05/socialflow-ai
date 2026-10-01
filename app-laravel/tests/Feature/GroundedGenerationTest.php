<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\GenerationRun;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Generation\GenerationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroundedGenerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'sk-test');
        config()->set('services.openai.model', 'gpt-5-mini');
        config()->set('services.openai.max_output_tokens', 750);
        config()->set('services.openai.store', false);
        Http::preventStrayRequests();
    }

    public function test_declared_water_resistance_is_supported_from_historical_snapshot(): void
    {
        [$user, $brand, $draft] = $this->draftWithSnapshot([
            $this->entry('entry-a', $this->metadata()),
        ]);
        $content = 'Stickers resistentes al agua.';
        $this->fakeStructuredResponse($content, [
            $this->claim('water_resistance', 'resistant', 'resistentes al agua'),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('succeeded', $run->status);
        $this->assertSame('passed', $run->grounding_status);
        $this->assertSame('SUPPORTED', $run->grounding_results[0]['status']);
        $this->assertSame('Adhesivo resistente al agua', $run->grounding_results[0]['evidence_excerpt']);
        $this->assertSame($content, $run->generated_content);
        $this->assertSame($content, $draft->fresh()->content);
        Http::assertSentCount(1);

        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Claims declarados: PASSED')
            ->assertSee('Los claims factuales declarados por el generador están respaldados.')
            ->assertSee('No verifica exhaustivamente todas las afirmaciones del contenido.')
            ->assertSee('Requiere aprobación humana antes de publicar.');
    }

    public function test_declared_durability_is_unknown_and_requires_review(): void
    {
        [$user, $brand, $draft] = $this->draftWithSnapshot([
            $this->entry('entry-a', $this->metadata()),
        ]);
        $this->fakeStructuredResponse('Stickers duraderos.', [
            $this->claim('durability', 'durable', 'duraderos'),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('succeeded', $run->status);
        $this->assertSame('passed', $run->evaluation_status);
        $this->assertSame('requires_review', $run->grounding_status);
        $this->assertSame('UNKNOWN', $run->grounding_results[0]['status']);

        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Grounding factual')
            ->assertSee('Claims declarados: REQUIRES REVIEW')
            ->assertSee('duraderos')
            ->assertSee('UNKNOWN')
            ->assertSee('sólo evalúa los claims factuales declarados')
            ->assertSee('Requiere aprobación humana antes de publicar.');
    }

    public function test_absent_allowed_use_with_open_coverage_is_unknown(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([
            $this->entry('entry-a', $this->metadata(coverage: 'open')),
        ]);
        $this->fakeStructuredResponse('Perfectos para botellas.', [
            $this->claim('allowed_use', 'bottles', 'para botellas'),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('requires_review', $run->grounding_status);
        $this->assertSame('UNKNOWN', $run->grounding_results[0]['status']);
    }

    public function test_absent_allowed_use_with_closed_coverage_is_unsupported(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([
            $this->entry('entry-a', $this->metadata(coverage: 'closed')),
        ]);
        $this->fakeStructuredResponse('Perfectos para botellas.', [
            $this->claim('allowed_use', 'bottles', 'para botellas'),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('requires_review', $run->grounding_status);
        $this->assertSame('UNSUPPORTED', $run->grounding_results[0]['status']);
        $this->assertSame('entry-a', $run->grounding_results[0]['knowledge_entry_id']);
    }

    public function test_conflicting_snapshot_values_require_review(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([
            $this->entry('entry-a', $this->metadata()),
            $this->entry('entry-b', $this->metadata(value: 'waterproof', phrase: 'impermeables', evidenceExcerpt: 'Material impermeable')),
        ]);
        $this->fakeStructuredResponse('Stickers resistentes al agua.', [
            $this->claim('water_resistance', 'resistant', 'resistentes al agua'),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('requires_review', $run->grounding_status);
        $this->assertSame('CONFLICT', $run->grounding_results[0]['status']);
        $this->assertSame(['resistant', 'waterproof'], $run->grounding_results[0]['conflicting_values']);
    }

    public function test_copy_without_declared_factual_claims_passes_with_empty_results(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([]);
        $this->fakeStructuredResponse('Dale vida a tus ideas.', []);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('succeeded', $run->status);
        $this->assertSame('passed', $run->grounding_status);
        $this->assertSame([], $run->grounding_results);
    }

    public function test_claim_text_absent_from_content_fails_closed(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([], 'Original');
        $this->fakeStructuredResponse('Stickers resistentes al agua.', [
            $this->claim('water_resistance', 'resistant', 'impermeables'),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertFailedWithoutGrounding($run, $draft);
        $this->assertStringContainsString('Declared factual claim text is absent from content.', $run->error);
        Http::assertSentCount(1);
    }

    public function test_claim_identifiers_must_remain_snake_case_without_normalization(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([]);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->response('Stickers resistentes al agua.', [$this->claim('water_resistance', 'resistente al agua', 'resistentes al agua')]))
                ->push($this->response('Stickers resistentes al agua.', [$this->claim('water_resistance', 'Resistant', 'resistentes al agua')]))
                ->push($this->response('Stickers resistentes al agua.', [$this->claim('water_resistance', 'water-resistant', 'resistentes al agua')])),
        ]);

        foreach (['resistente al agua', 'Resistant', 'water-resistant'] as $invalidValue) {
            $run = app(GenerationService::class)->generate($draft, $user, regenerate: true);

            $this->assertFailedWithoutGrounding($run, $draft);
            $this->assertStringContainsString('Factual claim value must be a stable snake_case identifier.', $run->error);
            $this->assertStringNotContainsString($invalidValue, $run->error);
        }

        Http::assertSentCount(3);
    }

    public function test_invalid_json_output_fails_closed(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([], 'Original');
        $this->fakeOutputText('{invalid-json');

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertFailedWithoutGrounding($run, $draft);
        $this->assertStringContainsString('output estructurado inválido', $run->error);
        Http::assertSentCount(1);
    }

    public function test_provider_failure_preserves_draft_and_leaves_grounding_not_evaluated(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([], 'Original');
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Provider failure']], 500),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertFailedWithoutGrounding($run, $draft);
        Http::assertSentCount(1);
    }

    public function test_grounding_uses_historical_snapshot_after_current_knowledge_changes(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $entry = KnowledgeEntry::create([
            'brand_id' => $brand->id,
            'created_by' => $user->id,
            'title' => 'Stickers resistentes',
            'content' => 'Adhesivo resistente al agua.',
            'status' => 'verified',
            'category' => 'product',
            'applicability' => 'global',
            'grounding_metadata' => $this->metadata(),
        ]);
        [, , $draft] = $this->draftWithSnapshot([
            $this->entry((string) $entry->getKey(), $entry->grounding_metadata),
        ], user: $user, brand: $brand);
        $entry->update([
            'content' => 'Stickers con acabado mate.',
            'grounding_metadata' => $this->metadata(
                predicate: 'finish',
                value: 'matte',
                phrase: 'acabado mate',
                evidenceExcerpt: 'acabado mate',
            ),
        ]);
        $this->fakeStructuredResponse('Stickers resistentes al agua.', [
            $this->claim('water_resistance', 'resistant', 'resistentes al agua'),
        ]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('passed', $run->grounding_status);
        $this->assertSame('SUPPORTED', $run->grounding_results[0]['status']);
        $this->assertSame('Adhesivo resistente al agua', $run->grounding_results[0]['evidence_excerpt']);
    }

    public function test_regeneration_preserves_each_content_claim_and_grounding_history(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot([
            $this->entry('entry-a', $this->metadata()),
        ]);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->response('Stickers resistentes al agua.', [
                    $this->claim('water_resistance', 'resistant', 'resistentes al agua'),
                ], 'resp_first'))
                ->push($this->response('Stickers duraderos.', [
                    $this->claim('durability', 'durable', 'duraderos'),
                ], 'resp_second')),
        ]);

        $first = app(GenerationService::class)->generate($draft, $user);
        $second = app(GenerationService::class)->generate($draft, $user, true);

        $this->assertSame(2, GenerationRun::count());
        $this->assertSame('Stickers resistentes al agua.', $first->fresh()->generated_content);
        $this->assertSame('SUPPORTED', $first->fresh()->grounding_results[0]['status']);
        $this->assertSame('resistentes al agua', $first->fresh()->grounding_results[0]['claim']['text']);
        $this->assertSame('Stickers duraderos.', $second->generated_content);
        $this->assertSame('UNKNOWN', $second->grounding_results[0]['status']);
        $this->assertSame('duraderos', $second->grounding_results[0]['claim']['text']);
        $this->assertSame('Stickers duraderos.', $draft->fresh()->content);
        Http::assertSentCount(2);
    }

    private function assertFailedWithoutGrounding(GenerationRun $run, Draft $draft): void
    {
        $this->assertSame('failed', $run->status);
        $this->assertSame('Original', $draft->fresh()->content);
        $this->assertSame('not_evaluated', $run->grounding_status);
        $this->assertNull($run->grounding_results);
        $this->assertNull($run->generated_content);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array{User, Brand, Draft, ContextSnapshot}
     */
    private function draftWithSnapshot(
        array $entries,
        string $content = 'Original',
        ?User $user = null,
        ?Brand $brand = null,
    ): array {
        $user ??= User::factory()->create();
        $brand ??= Brand::factory()->create();

        if (! $user->brands()->whereKey($brand->getKey())->exists()) {
            $user->brands()->attach($brand, ['role' => 'owner']);
        }

        $draft = Draft::create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'title' => 'Grounded draft',
            'content' => $content,
            'status' => 'draft',
        ]);
        $snapshot = ContextSnapshot::create([
            'draft_id' => $draft->id,
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'query' => 'Promocionar stickers',
            'relevant_knowledge' => $entries,
            'brand_context' => [],
            'policies' => [],
            'restrictions' => [],
            'pending_knowledge' => [],
            'sources' => [],
            'warnings' => [],
            'missing_information' => [],
            'matches' => [],
        ]);

        return [$user, $brand, $draft, $snapshot];
    }

    /** @param list<array{subject: string, predicate: string, value: string, text: string}> $claims */
    private function fakeStructuredResponse(string $content, array $claims): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->response($content, $claims)),
        ]);
    }

    private function fakeOutputText(string $outputText): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->responseData($outputText)),
        ]);
    }

    /**
     * @param  list<array{subject: string, predicate: string, value: string, text: string}>  $claims
     * @return array<string, mixed>
     */
    private function response(string $content, array $claims, string $id = 'resp_grounded'): array
    {
        return $this->responseData(json_encode([
            'content' => $content,
            'factual_claims' => $claims,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $id);
    }

    /** @return array<string, mixed> */
    private function responseData(string $outputText, string $id = 'resp_grounded'): array
    {
        return [
            'id' => $id,
            'model' => 'gpt-5-mini',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'content' => [[
                    'type' => 'output_text',
                    'text' => $outputText,
                ]],
            ]],
            'usage' => [
                'input_tokens' => 100,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens' => 50,
            ],
        ];
    }

    /** @return array{subject: string, predicate: string, value: string, text: string} */
    private function claim(string $predicate, string $value, string $text): array
    {
        return [
            'subject' => 'stickers',
            'predicate' => $predicate,
            'value' => $value,
            'text' => $text,
        ];
    }

    /** @return array<string, mixed> */
    private function entry(string $id, array $metadata): array
    {
        return [
            'id' => $id,
            'title' => 'Stickers',
            'content' => $metadata['claims'][0]['evidence_excerpt'],
            'status' => 'verified',
            'source' => 'Fuente histórica',
            'grounding_metadata' => $metadata,
        ];
    }

    /** @return array<string, mixed> */
    private function metadata(
        string $coverage = 'open',
        string $predicate = 'water_resistance',
        string $value = 'resistant',
        string $phrase = 'resistentes al agua',
        string $evidenceExcerpt = 'Adhesivo resistente al agua',
    ): array {
        return [
            'subject' => 'stickers',
            'claims' => [[
                'predicate' => $predicate,
                'value' => $value,
                'phrases' => [$phrase],
                'evidence_excerpt' => $evidenceExcerpt,
            ]],
            'allowed_uses' => [
                'values' => [],
                'coverage' => $coverage,
            ],
        ];
    }
}
