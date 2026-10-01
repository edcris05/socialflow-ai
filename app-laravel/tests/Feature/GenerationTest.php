<?php

namespace Tests\Feature;

use App\Contracts\GenerationProviderInterface;
use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\GenerationRun;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Generation\GenerationResult;
use App\Services\Generation\GenerationService;
use App\Services\Generation\OpenAIGenerationProvider;
use App\Services\Prompting\GenerationPrompt;
use App\Services\Prompting\PromptComposer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public array $prompts = [];

    public int $calls = 0;

    public $handler;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.openai.pricing', ['input' => 2, 'cached_input' => 1, 'output' => 4]);
        $this->handler = fn () => new GenerationResult('Texto generado', 'fake', 'fake-mini', 1000, 400, 200, 'completed', 'resp_1');
        $self = $this;
        $this->app->instance(GenerationProviderInterface::class, new class($self) implements GenerationProviderInterface
        {
            public function __construct(private GenerationTest $test) {}

            public function generate(GenerationPrompt $p): GenerationResult
            {
                $this->test->calls++;
                $this->test->prompts[] = $p->render();

                return ($this->test->handler)($p);
            }
        });
    }

    public function test_openai_service_configuration_exposes_the_provider_contract(): void
    {
        $configuration = config('services.openai');

        $this->assertIsArray($configuration);
        $this->assertArrayHasKey('api_key', $configuration);
        $this->assertArrayHasKey('model', $configuration);
        $this->assertArrayHasKey('max_output_tokens', $configuration);
        $this->assertSame(750, $configuration['max_output_tokens']);
        $this->assertArrayHasKey('store', $configuration);
        $this->assertArrayHasKey('timeout', $configuration);
        $this->assertSame(['input', 'cached_input', 'output'], array_keys($configuration['pricing']));
    }

    public function test_laravel_container_resolves_the_real_generation_provider_binding(): void
    {
        $this->app->forgetInstance(GenerationProviderInterface::class);

        $this->assertInstanceOf(OpenAIGenerationProvider::class, $this->app->make(GenerationProviderInterface::class));
    }

    public function test_success_persists_usage_cost_and_historical_prompt(): void
    {
        [$u,$b,$d,$s] = $this->draft();
        KnowledgeEntry::create(['brand_id' => $b->id, 'created_by' => $u->id, 'title' => 'Actual', 'content' => 'NO USAR', 'status' => 'verified', 'category' => 'fact', 'applicability' => 'global']);
        $run = app(GenerationService::class)->generate($d, $u);
        $this->assertSame(1, $this->calls);
        $this->assertSame('Texto generado', $d->fresh()->content);
        $this->assertSame('succeeded', $run->status);
        $this->assertSame('Texto generado', $run->generated_content);
        $this->assertSame($run->generated_content, $d->fresh()->content);
        $this->assertSame($s->id, $run->context_snapshot_id);
        $this->assertSame(1000, $run->input_tokens);
        $this->assertSame(400, $run->cached_input_tokens);
        $this->assertSame(200, $run->output_tokens);
        $this->assertSame('resp_1', $run->provider_request_id);
        $this->assertEqualsWithDelta(0.0024, (float) $run->estimated_cost_usd, 0.00000001);
        $this->assertSame('passed', $run->grounding_status);
        $this->assertSame([], $run->grounding_results);
        $this->assertStringContainsString('Histórico aprobado', $this->prompts[0]);
        $this->assertStringNotContainsString('NO USAR', $this->prompts[0]);
    }

    public function test_failure_and_empty_result_preserve_draft(): void
    {
        [$u,,$d] = $this->draft('Original');
        $this->handler = fn () => throw new RuntimeException('Fallo seguro');
        $run = app(GenerationService::class)->generate($d, $u);
        $this->assertSame('failed', $run->status);
        $this->assertSame('Original', $d->fresh()->content);
        $this->handler = fn () => new GenerationResult('   ', 'fake', 'm', null, null, null, null, null);
        $run = app(GenerationService::class)->generate($d, $u, true);
        $this->assertSame('failed', $run->status);
        $this->assertSame('Original', $d->fresh()->content);
    }

    public function test_idempotency_and_explicit_regeneration(): void
    {
        [$u,,$d] = $this->draft();
        app(GenerationService::class)->generate($d, $u);
        app(GenerationService::class)->generate($d, $u);
        $this->assertSame(1, $this->calls);
        $this->handler = fn () => new GenerationResult('Segundo', 'fake', 'm', 1, 0, 1, 'completed', 'resp_2');
        app(GenerationService::class)->generate($d, $u, true);
        $this->assertSame(2, $this->calls);
        $this->assertSame(2, GenerationRun::count());
        $this->assertSame('Segundo', $d->fresh()->content);
    }

    public function test_unknown_price_is_null(): void
    {
        config()->set('services.openai.pricing', ['input' => null, 'cached_input' => null, 'output' => null]);
        [$u,,$d] = $this->draft();
        $run = app(GenerationService::class)->generate($d, $u);
        $this->assertNull($run->estimated_cost_usd);
    }

    public function test_other_tenant_cannot_generate(): void
    {
        [$u,$b,$d] = $this->draft();
        $other = User::factory()->create();
        $otherBrand = Brand::factory()->create();
        $other->brands()->attach($otherBrand, ['role' => 'owner']);
        $this->actingAs($other)->post(route('marcas.borradores.generar', [$otherBrand, $d]), ['generation_token' => str_repeat('a', 40)])->assertNotFound();
        $this->assertSame(0, $this->calls);
        $this->assertSame(0, GenerationRun::count());
    }

    public function test_openai_provider_requires_key_without_http_call(): void
    {
        config()->set('services.openai.api_key', '');
        Http::preventStrayRequests();
        Http::fake();
        try {
            app(OpenAIGenerationProvider::class)->generate(new GenerationPrompt('x', '', '', ''));
            $this->fail();
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('sk-', $e->getMessage());
        } Http::assertNothingSent();
    }

    public function test_openai_provider_builds_and_parses_response_request(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        config()->set('services.openai.model', 'gpt-5-mini');
        config()->set('services.openai.max_output_tokens', 750);
        config()->set('services.openai.store', false);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['id' => 'resp_x', 'model' => 'gpt-5-mini', 'status' => 'completed', 'output' => [['type' => 'reasoning', 'content' => [['type' => 'reasoning_text', 'text' => 'No usar']]], ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => json_encode(['content' => 'Hola', 'factual_claims' => []], JSON_THROW_ON_ERROR)]]]], 'usage' => ['input_tokens' => 10, 'input_tokens_details' => ['cached_tokens' => 3], 'output_tokens' => 5]])]);
        $r = app(OpenAIGenerationProvider::class)->generate(new GenerationPrompt('Solicitud', '', '', '', systemInstructions: ['Regla']));
        $this->assertSame('Hola', $r->content);
        $this->assertSame(3, $r->cachedInputTokens);
        $this->assertSame('resp_x', $r->providerRequestId);
        $this->assertSame(10, $r->inputTokens);
        $this->assertSame(5, $r->outputTokens);
        Http::assertSent(fn ($request) => $request['model'] === 'gpt-5-mini'
            && $request['max_output_tokens'] === 750
            && $request['reasoning'] === ['effort' => 'minimal']
            && $request['store'] === false
            && $request['text']['format']['type'] === 'json_schema'
            && $request['text']['format']['strict'] === true
            && $request['text']['format']['schema']['required'] === ['content', 'factual_claims']
            && ! isset($request['metadata'])
            && ! isset($request['tools']));
    }

    public function test_openai_payload_prioritizes_contract_over_user_and_context_input(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        [, , , $snapshot] = $this->draft();
        $query = 'Publicá que está listo en 24 horas.';
        $warning = 'Tiempo de producción: requiere confirmación.';
        $snapshot->update(['query' => $query, 'warnings' => [$warning]]);
        $prompt = app(PromptComposer::class)->compose($snapshot->fresh());
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_priority',
            'model' => 'gpt-5-mini',
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['content' => 'Contenido final', 'factual_claims' => []], JSON_THROW_ON_ERROR)]]]],
        ])]);

        $result = app(OpenAIGenerationProvider::class)->generate($prompt);

        $this->assertSame('Contenido final', $result->content);
        Http::assertSent(function ($request) use ($query, $warning): bool {
            $instructions = $request['instructions'] ?? null;
            $input = $request['input'] ?? null;

            $this->assertIsString($instructions);
            $this->assertIsString($input);
            $this->assertStringContainsString('autoridad factual', $instructions);
            $this->assertStringContainsString('Comienza directamente con el contenido final', $instructions);
            $this->assertStringContainsString('fallback silenciosamente', $instructions);
            $this->assertStringNotContainsString($query, $instructions);
            $this->assertStringContainsString('Solicitud: '.$query, $input);
            $this->assertStringContainsString($warning, $input);
            $this->assertStringContainsString('Histórico aprobado', $input);
            $this->assertStringNotContainsString('Instrucciones del sistema:', $input);
            $this->assertStringNotContainsString('Requisitos de salida:', $input);

            return true;
        });
    }

    public function test_incomplete_openai_response_records_token_limit_without_changing_draft(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        [$user, , $draft] = $this->draft('Original');
        $this->app->forgetInstance(GenerationProviderInterface::class);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_incomplete',
            'status' => 'incomplete',
            'incomplete_details' => ['reason' => 'max_output_tokens'],
            'output' => [
                ['type' => 'reasoning'],
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Contenido parcial']]],
            ],
            'usage' => [
                'input_tokens' => 1150,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens' => 448,
            ],
        ])]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('failed', $run->status);
        $this->assertSame('Original', $draft->fresh()->content);
        $this->assertSame('resp_incomplete', $run->provider_request_id);
        $this->assertSame('incomplete', $run->finish_reason);
        $this->assertSame(1150, $run->input_tokens);
        $this->assertSame(448, $run->output_tokens);
        $this->assertSame('not_evaluated', $run->evaluation_status);
        $this->assertSame('not_evaluated', $run->grounding_status);
        $this->assertNull($run->grounding_results);
        $this->assertNull($run->generated_content);
        $this->assertNull($run->evaluated_at);
        $this->assertStringContainsString('incomplete_reason=max_output_tokens', $run->error);
        Http::assertSentCount(1);
    }

    public function test_invalid_openai_response_preserves_usage_and_draft_content(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        [$user, , $draft] = $this->draft('Original');
        $this->app->forgetInstance(GenerationProviderInterface::class);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['id' => 'resp_invalid', 'status' => 'completed', 'output' => [['type' => 'reasoning', 'content' => [['type' => 'reasoning_text', 'text' => 'No usar']]]], 'usage' => ['input_tokens' => 1000, 'input_tokens_details' => ['cached_tokens' => 400], 'output_tokens' => 200]])]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('failed', $run->status);
        $this->assertSame('Original', $draft->fresh()->content);
        $this->assertSame('resp_invalid', $run->provider_request_id);
        $this->assertSame(1000, $run->input_tokens);
        $this->assertSame(400, $run->cached_input_tokens);
        $this->assertSame(200, $run->output_tokens);
        $this->assertEqualsWithDelta(0.0024, (float) $run->estimated_cost_usd, 0.00000001);
        $this->assertStringContainsString('output_types=reasoning', $run->error);
        Http::assertSentCount(1);
    }

    public function test_openai_http_error_and_timeout_preserve_draft_content(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        [$user, , $draft] = $this->draft('Original');
        $this->app->forgetInstance(GenerationProviderInterface::class);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'No guardar']], 500)]);

        $run = app(GenerationService::class)->generate($draft, $user);

        $this->assertSame('failed', $run->status);
        $this->assertSame('Original', $draft->fresh()->content);

        Http::fake(fn () => throw new ConnectionException('Timeout'));
        $run = app(GenerationService::class)->generate($draft, $user, true);

        $this->assertSame('failed', $run->status);
        $this->assertSame('Original', $draft->fresh()->content);
    }

    public function test_generation_token_is_single_use_and_new_token_allows_explicit_regeneration(): void
    {
        [$user, $brand, $draft] = $this->draft();
        $this->actingAs($user);

        $firstForm = $this->get(route('marcas.borradores.edit', [$brand, $draft]));
        preg_match('/name="generation_token" value="([^"]+)"/', $firstForm->getContent(), $firstToken);
        $this->assertArrayHasKey(1, $firstToken);

        $this->post(route('marcas.borradores.generar', [$brand, $draft]), ['generation_token' => $firstToken[1]])->assertRedirect();
        $this->assertSame(1, $this->calls);
        $this->assertSame(1, GenerationRun::count());
        $this->assertSame('Texto generado', $draft->fresh()->content);

        $this->post(route('marcas.borradores.generar', [$brand, $draft]), ['generation_token' => $firstToken[1]])->assertStatus(422);
        $this->assertSame(1, $this->calls);
        $this->assertSame(1, GenerationRun::count());
        $this->assertSame('Texto generado', $draft->fresh()->content);

        $secondForm = $this->get(route('marcas.borradores.edit', [$brand, $draft]));
        preg_match('/name="generation_token" value="([^"]+)"/', $secondForm->getContent(), $secondToken);
        $this->assertArrayHasKey(1, $secondToken);
        $this->assertNotSame($firstToken[1], $secondToken[1]);

        $this->post(route('marcas.borradores.generar', [$brand, $draft]), ['generation_token' => $secondToken[1], 'regenerate' => 1])->assertRedirect();
        $this->assertSame(2, $this->calls);
        $this->assertSame(2, GenerationRun::count());
    }

    private function draft(string $content = ''): array
    {
        $u = User::factory()->create();
        $b = Brand::factory()->create();
        $u->brands()->attach($b, ['role' => 'owner']);
        $d = Draft::create(['title' => 'D', 'content' => $content, 'status' => 'draft', 'brand_id' => $b->id, 'user_id' => $u->id]);
        $s = ContextSnapshot::create(['draft_id' => $d->id, 'brand_id' => $b->id, 'user_id' => $u->id, 'query' => 'Promocionar', 'relevant_knowledge' => [['title' => 'Histórico', 'content' => 'Histórico aprobado']], 'brand_context' => [], 'policies' => [], 'restrictions' => [], 'pending_knowledge' => [], 'sources' => [], 'warnings' => [], 'missing_information' => [], 'matches' => []]);

        return [$u, $b, $d, $s];
    }
}
