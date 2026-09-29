<?php

namespace Tests\Feature;

use App\Contracts\GenerationEvaluatorInterface;
use App\Contracts\GenerationProviderInterface;
use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\GenerationRun;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Generation\DeterministicGenerationEvaluator;
use App\Services\Generation\EvaluationResult;
use App\Services\Generation\GenerationResult;
use App\Services\Generation\GenerationService;
use App\Services\Prompting\GenerationPrompt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GenerationEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public string $generatedContent = 'Contenido generado sin alertas.';

    public int $providerCalls = 0;

    public ?string $evaluatedContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        $this->app->instance(GenerationProviderInterface::class, new class($test) implements GenerationProviderInterface
        {
            public function __construct(private GenerationEvaluationTest $test) {}

            public function generate(GenerationPrompt $prompt): GenerationResult
            {
                $this->test->providerCalls++;

                return new GenerationResult($this->test->generatedContent, 'fake', 'fake-mini', 10, 0, 5, 'completed', 'resp_guardrail');
            }
        });
    }

    public function test_content_without_automatic_alerts_passes_and_negative_phrases_do_not_false_positive(): void
    {
        [, , , $snapshot] = $this->draftWithSnapshot([
            'Precio antes de publicacion: requiere confirmacion.',
            'Stock actual: requiere confirmacion.',
            'Tiempo de produccion: requiere confirmacion.',
        ]);

        $result = app(DeterministicGenerationEvaluator::class)->evaluate(
            'Escribinos para consultar precio. Consultanos disponibilidad. Coordinamos el tiempo de produccion.',
            $snapshot,
        );

        $this->assertSame('passed', $result->status);
        $this->assertSame([], $result->violations);
    }

    public function test_price_stock_time_and_unsupported_promotion_are_stable_violations(): void
    {
        [, , , $snapshot] = $this->draftWithSnapshot([
            'Precio antes de publicacion: requiere confirmacion.',
            'Stock actual: requiere confirmacion.',
            'Tiempo de produccion: requiere confirmacion.',
        ]);

        $result = app(DeterministicGenerationEvaluator::class)->evaluate(
            'Stickers a $4500, tenemos stock, entrega en 24 horas y 20% OFF esta semana.',
            $snapshot,
        );

        $this->assertSame('requires_review', $result->status);
        $this->assertSame([
            'PRICE_REQUIRES_CONFIRMATION',
            'STOCK_REQUIRES_CONFIRMATION',
            'PRODUCTION_TIME_REQUIRES_CONFIRMATION',
            'UNSUPPORTED_PROMOTION',
        ], array_column($result->violations, 'code'));
        $this->assertContains('warning: precio requiere confirmacion', array_column($result->violations, 'evidence'));
    }

    public function test_time_promises_detect_singular_and_plural_without_false_positives(): void
    {
        [, , , $snapshot] = $this->draftWithSnapshot([
            'Tiempo de produccion: requiere confirmacion.',
        ]);

        foreach ([
            'Listo en 24 horas.',
            'Lista en 24 horas.',
            'Listos en 24 horas.',
            'Listas en 24 horas.',
            'Quedan listos en 2 dias.',
        ] as $content) {
            $result = app(DeterministicGenerationEvaluator::class)->evaluate($content, $snapshot);

            $this->assertSame('requires_review', $result->status, $content);
            $this->assertSame(['PRODUCTION_TIME_REQUIRES_CONFIRMATION'], array_column($result->violations, 'code'), $content);
        }

        foreach ([
            'Consultanos por los tiempos de produccion.',
            'El tiempo de produccion se coordina segun el pedido.',
            'Escribinos para consultar cuando puede estar listo.',
            'Cuando este listo te avisamos.',
        ] as $content) {
            $result = app(DeterministicGenerationEvaluator::class)->evaluate($content, $snapshot);

            $this->assertSame('passed', $result->status, $content);
            $this->assertSame([], $result->violations, $content);
        }
    }

    public function test_future_idea_cannot_be_presented_as_currently_available(): void
    {
        [, , , $snapshot] = $this->draftWithSnapshot([], [
            ['title' => 'Idea futura - Cuadernillo QR', 'content' => 'Pendiente de evaluar.', 'category' => 'future_idea'],
        ]);

        $result = app(DeterministicGenerationEvaluator::class)->evaluate(
            'Nuestro nuevo producto Cuadernillo QR ya esta disponible.',
            $snapshot,
        );

        $this->assertSame('requires_review', $result->status);
        $this->assertSame('FUTURE_IDEA_PRESENTED_AS_AVAILABLE', $result->violations[0]['code']);
    }

    public function test_succeeded_generation_requires_review_and_preserves_generated_draft_content(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot(['Stock actual: requiere confirmacion.']);
        $this->generatedContent = 'Tenemos stock disponible ahora.';

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame(1, $this->providerCalls);
        $this->assertSame('succeeded', $run->status);
        $this->assertSame('Tenemos stock disponible ahora.', $run->generated_content);
        $this->assertSame('requires_review', $run->evaluation_status);
        $this->assertSame('Tenemos stock disponible ahora.', $draft->fresh()->content);
        $this->assertNotNull($run->evaluated_at);
        $this->assertSame('STOCK_REQUIRES_CONFIRMATION', $run->evaluation_violations[0]['code']);
    }

    public function test_evaluator_receives_the_exact_content_persisted_in_run_and_draft(): void
    {
        [$user, , $draft] = $this->draftWithSnapshot();
        $this->generatedContent = "  Contenido exacto del provider.\n";
        $test = $this;
        $this->app->instance(GenerationEvaluatorInterface::class, new class($test) implements GenerationEvaluatorInterface
        {
            public function __construct(private GenerationEvaluationTest $test) {}

            public function evaluate(string $content, ContextSnapshot $snapshot): EvaluationResult
            {
                $this->test->evaluatedContent = $content;

                return EvaluationResult::passed();
            }
        });

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame($this->generatedContent, $this->evaluatedContent);
        $this->assertSame($this->generatedContent, $run->generated_content);
        $this->assertSame($this->generatedContent, $draft->fresh()->content);
    }

    public function test_evaluation_uses_historical_snapshot_not_current_knowledge(): void
    {
        [$user, $brand, $draft] = $this->draftWithSnapshot();
        KnowledgeEntry::create([
            'brand_id' => $brand->id,
            'created_by' => $user->id,
            'title' => 'Stock actual',
            'content' => 'No hay stock documentado.',
            'status' => 'verified',
            'category' => 'fact',
            'applicability' => 'global',
        ]);
        $this->generatedContent = 'Tenemos stock disponible ahora.';

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('passed', $run->evaluation_status);
        $this->assertSame([], $run->evaluation_violations);
    }

    public function test_regeneration_preserves_each_evaluated_output_as_historical_evidence(): void
    {
        [$user, $brand, $draft] = $this->draftWithSnapshot([
            'Precio antes de publicacion: requiere confirmacion.',
            'Stock actual: requiere confirmacion.',
            'Tiempo de produccion: requiere confirmacion.',
        ]);
        $firstOutput = 'Tenemos stock disponible. Precio $3500. Listos en 24 horas.';
        $secondOutput = 'Consultanos precio, disponibilidad y tiempos de produccion.';
        $this->actingAs($user);

        $this->generatedContent = $firstOutput;
        $firstForm = $this->get(route('marcas.borradores.edit', [$brand, $draft]));
        preg_match('/name="generation_token" value="([^"]+)"/', $firstForm->getContent(), $firstToken);
        $this->post(route('marcas.borradores.generar', [$brand, $draft]), [
            'generation_token' => $firstToken[1],
        ])->assertRedirect()->assertSessionHas('status', 'Contenido generado.');
        $first = GenerationRun::query()->sole();

        $this->generatedContent = $secondOutput;
        $secondForm = $this->get(route('marcas.borradores.edit', [$brand, $draft]));
        preg_match('/name="generation_token" value="([^"]+)"/', $secondForm->getContent(), $secondToken);
        $this->post(route('marcas.borradores.generar', [$brand, $draft]), [
            'generation_token' => $secondToken[1],
            'regenerate' => 1,
        ])->assertRedirect()->assertSessionHas('status', 'Contenido generado.');
        $second = GenerationRun::query()->where('id', '!=', $first->getKey())->sole();

        $this->assertSame(2, $this->providerCalls);
        $this->assertSame(2, GenerationRun::count());
        $this->assertSame($firstOutput, $first->generated_content);
        $this->assertSame('requires_review', $first->evaluation_status);
        $this->assertSame([
            'PRICE_REQUIRES_CONFIRMATION',
            'STOCK_REQUIRES_CONFIRMATION',
            'PRODUCTION_TIME_REQUIRES_CONFIRMATION',
        ], array_column($first->evaluation_violations, 'code'));
        $this->assertSame($secondOutput, $second->generated_content);
        $this->assertSame('passed', $second->evaluation_status);
        $this->assertSame($secondOutput, $draft->fresh()->content);
        $this->assertSame($firstOutput, $first->fresh()->generated_content);
        $this->assertSame('requires_review', $first->fresh()->evaluation_status);
    }

    public function test_historical_run_without_generated_content_remains_valid(): void
    {
        [$user, $brand, $draft, $snapshot] = $this->draftWithSnapshot();

        $run = GenerationRun::create([
            'brand_id' => $brand->id,
            'draft_id' => $draft->id,
            'context_snapshot_id' => $snapshot->id,
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'operation' => 'draft_content',
            'status' => 'succeeded',
            'evaluation_status' => 'not_evaluated',
        ]);

        $this->assertNull($run->generated_content);
    }

    public function test_draft_ui_shows_precise_evaluation_language(): void
    {
        [$user, $brand, $draft] = $this->draftWithSnapshot(['Stock actual: requiere confirmacion.']);
        $this->generatedContent = 'Tenemos stock disponible ahora.';
        app(GenerationService::class)->generate($draft, $user);

        $this->actingAs($user)
            ->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Evaluacion del contenido')
            ->assertSee('Generacion '.$draft->generationRuns()->latest()->firstOrFail()->getKey())
            ->assertSee('Requiere revision')
            ->assertSee('El contenido afirma disponibilidad o stock pero el contexto requiere confirmarlo.');
    }

    /**
     * @param  array<int, string>  $warnings
     * @param  array<int, array<string, mixed>>  $relevantKnowledge
     * @return array{User, Brand, Draft, ContextSnapshot}
     */
    private function draftWithSnapshot(array $warnings = [], array $relevantKnowledge = []): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $draft = Draft::create([
            'title' => 'Draft',
            'content' => 'Original',
            'status' => 'draft',
            'brand_id' => $brand->id,
            'user_id' => $user->id,
        ]);
        $snapshot = ContextSnapshot::create([
            'draft_id' => $draft->id,
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'query' => 'Promocionar stickers',
            'relevant_knowledge' => $relevantKnowledge,
            'brand_context' => [],
            'policies' => [],
            'restrictions' => [],
            'pending_knowledge' => [],
            'sources' => [],
            'warnings' => $warnings,
            'missing_information' => [],
            'matches' => [],
        ]);

        return [$user, $brand, $draft, $snapshot];
    }
}
