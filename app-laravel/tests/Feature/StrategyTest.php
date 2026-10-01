<?php

namespace Tests\Feature;

use App\Contracts\StrategyProviderInterface;
use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\GenerationRun;
use App\Models\KnowledgeEntry;
use App\Models\StrategyRun;
use App\Models\User;
use App\Services\Strategy\ContentSuggestion;
use App\Services\Strategy\OpenAIStrategyProvider;
use App\Services\Strategy\StrategyContext;
use App\Services\Strategy\StrategyContextBuilder;
use App\Services\Strategy\StrategyResult;
use App\Services\Strategy\StrategyService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StrategyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public int $calls = 0;

    /** @var list<StrategyContext> */
    public array $contexts = [];

    public $handler;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.pricing', [
            'input' => 0.25,
            'cached_input' => 0.025,
            'output' => 2,
        ]);
        $this->handler = fn (): StrategyResult => $this->strategyResult();
        $self = $this;
        $this->app->instance(StrategyProviderInterface::class, new class($self) implements StrategyProviderInterface
        {
            public function __construct(private StrategyTest $test) {}

            public function generate(StrategyContext $context): StrategyResult
            {
                $this->test->calls++;
                $this->test->contexts[] = $context;

                return ($this->test->handler)($context);
            }
        });
    }

    public function test_generates_three_persisted_suggestions_from_authorized_context_with_single_use_token(): void
    {
        [$user, $brand] = $this->brand();
        $this->entry($brand, 'Stickers resistentes al agua', 'Stickers confirmados resistentes al agua.', 'product');
        $this->entry($brand, 'Promoción pendiente', '20% OFF todavía no aprobado.', 'price', 'pending');
        $this->entry($brand, 'Agendas', 'Agendas personalizadas para el futuro.', 'future_idea');

        $token = $this->strategyToken($brand);
        $this->post(route('marcas.estrategia.store', $brand), ['strategy_token' => $token])
            ->assertRedirect(route('marcas.estrategia.index', $brand))
            ->assertSessionHas('status', 'Se generaron tres sugerencias estratégicas.');

        $run = StrategyRun::query()->sole();
        $this->assertSame(1, $this->calls);
        $this->assertSame('succeeded', $run->status);
        $this->assertCount(3, $run->suggestions);
        $this->assertSame($brand->getKey(), $run->brand_id);
        $this->assertSame($user->getKey(), $run->user_id);
        $this->assertSame(100, $run->input_tokens);
        $this->assertSame(20, $run->cached_input_tokens);
        $this->assertSame(50, $run->output_tokens);
        $this->assertEqualsWithDelta(0.0001205, (float) $run->estimated_cost_usd, 0.00000001);

        $serializedContext = json_encode($run->context, JSON_THROW_ON_ERROR);
        $serializedSuggestions = json_encode($run->suggestions, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Stickers confirmados resistentes al agua.', $serializedContext);
        $this->assertStringNotContainsString('20% OFF todavía no aprobado.', $serializedContext);
        $this->assertStringNotContainsString('Agendas personalizadas para el futuro.', $serializedContext);
        $this->assertStringContainsString('pending_not_authorized', $serializedContext);
        $this->assertStringContainsString('future_idea_not_available', $serializedContext);
        $this->assertStringNotContainsString('$', $serializedSuggestions);
        $this->assertStringNotContainsString('stock disponible', mb_strtolower($serializedSuggestions));
        $this->assertStringNotContainsString('24 horas', mb_strtolower($serializedSuggestions));

        $this->post(route('marcas.estrategia.store', $brand), ['strategy_token' => $token])
            ->assertUnprocessable();
        $this->assertSame(1, $this->calls);
        $this->assertSame(1, StrategyRun::query()->count());
    }

    public function test_openai_provider_sends_strict_structured_output_in_one_request(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        config()->set('services.openai.model', 'gpt-5-mini');
        config()->set('services.openai.strategy_max_output_tokens', 1500);
        config()->set('services.openai.store', false);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAIResponse()),
        ]);
        $context = new StrategyContext(
            ['name' => 'Marca'],
            [['title' => 'Producto confirmado', 'content' => 'Dato autorizado']],
            [],
            [['title' => 'No inventar', 'content' => 'No inventar stock ni precio']],
            [['title' => 'Idea futura', 'reason' => 'future_idea_not_available']],
            ['No utilizar ideas futuras.'],
            [],
            ['Tema anterior'],
        );

        $result = app(OpenAIStrategyProvider::class)->generate($context);

        $this->assertCount(3, $result->suggestions);
        $this->assertSame('resp_strategy', $result->providerRequestId);
        Http::assertSent(function ($request): bool {
            $input = json_decode($request['input'], true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame('gpt-5-mini', $request['model']);
            $this->assertSame(1500, $request['max_output_tokens']);
            $this->assertSame(['effort' => 'minimal'], $request['reasoning']);
            $this->assertFalse($request['store']);
            $this->assertSame('json_schema', $request['text']['format']['type']);
            $this->assertTrue($request['text']['format']['strict']);
            $this->assertSame(3, $request['text']['format']['schema']['properties']['suggestions']['minItems']);
            $this->assertSame(3, $request['text']['format']['schema']['properties']['suggestions']['maxItems']);
            $this->assertFalse($request['text']['format']['schema']['additionalProperties']);
            $this->assertStringContainsString('No inventes productos', $request['instructions']);
            $this->assertStringContainsString('future_idea', $request['instructions']);
            $this->assertStringContainsString('No afirmes rendimiento', $request['instructions']);
            $this->assertSame(['Tema anterior'], $input['recent_topics']);
            $this->assertSame('future_idea_not_available', $input['unusable_knowledge'][0]['reason']);

            return true;
        });
        Http::assertSentCount(1);
    }

    public function test_invalid_json_fails_closed_without_partial_suggestions(): void
    {
        [$user, $brand] = $this->brand();
        $this->entry($brand, 'Producto', 'Producto confirmado.', 'product');
        $this->useRealProvider();
        config()->set('services.openai.api_key', 'sk-test');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAIResponse('{invalid')),
        ]);

        $run = app(StrategyService::class)->generate($brand, $user);

        $this->assertSame('failed', $run->status);
        $this->assertNull($run->suggestions);
        $this->assertSame('resp_strategy', $run->provider_request_id);
        $this->assertSame(100, $run->input_tokens);
        $this->assertStringContainsString('estructuradas inválidas', $run->error);
        $this->assertSame(0, Draft::query()->count());
        Http::assertSentCount(1);
    }

    public function test_provider_http_failure_keeps_a_failed_auditable_run(): void
    {
        [$user, $brand] = $this->brand();
        $this->useRealProvider();
        config()->set('services.openai.api_key', 'sk-test');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'remote']], 500),
        ]);

        $run = app(StrategyService::class)->generate($brand, $user);

        $this->assertSame('failed', $run->status);
        $this->assertNull($run->suggestions);
        $this->assertStringContainsString('no pudo generar sugerencias', $run->error);
        $this->assertSame($brand->getKey(), $run->brand_id);
        $this->assertSame($user->getKey(), $run->user_id);
        Http::assertSentCount(1);
    }

    public function test_suggestion_creates_a_draft_and_snapshot_with_exact_generation_query(): void
    {
        [$user, $brand] = $this->brand();
        $this->entry($brand, 'Stickers resistentes al agua', 'Adhesivo resistente al agua.', 'product');
        $run = $this->persistedRun($user, $brand);
        Http::preventStrayRequests();

        $response = $this->post(route('marcas.estrategia.borradores.store', [$brand, $run, 0]));

        $draft = Draft::query()->sole();
        $response->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));
        $this->assertSame('Borrador: Stickers resistentes al agua', $draft->title);
        $this->assertSame('', $draft->content);
        $this->assertSame(Draft::STATUS_DRAFT, $draft->status);
        $this->assertSame('Creá un post breve sobre stickers resistentes al agua.', $draft->contextSnapshot->query);
        $this->assertSame($run->suggestions, $run->fresh()->suggestions);
        $this->assertSame(0, GenerationRun::query()->count());
        $this->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Ver prompt')
            ->assertSee('Generar contenido');
        Http::assertNothingSent();
    }

    public function test_cross_tenant_users_cannot_generate_view_or_reuse_strategy_runs(): void
    {
        [$owner, $otherBrand] = $this->brand();
        $otherRun = $this->persistedRun($owner, $otherBrand);
        [$user, $brand] = $this->brand();
        $this->actingAs($user);
        $token = str_repeat('a', 40);

        $this->get(route('marcas.estrategia.index', $otherBrand))->assertNotFound();
        $this->post(route('marcas.estrategia.store', $otherBrand), ['strategy_token' => $token])
            ->assertNotFound();
        $this->post(route('marcas.estrategia.borradores.store', [$brand, $otherRun, 0]))
            ->assertNotFound();

        $this->assertSame(0, $this->calls);
        $this->assertSame(0, Draft::query()->count());
        $this->assertSame($otherBrand->getKey(), $otherRun->fresh()->brand_id);
    }

    public function test_recent_draft_queries_and_strategy_topics_are_included_without_other_users_history(): void
    {
        [$user, $brand] = $this->brand();
        $otherUser = User::factory()->create();
        $otherUser->brands()->attach($brand, ['role' => 'editor']);
        $this->draftWithSnapshot($user, $brand, 'Tema propio', 'Consulta propia');
        $this->draftWithSnapshot($otherUser, $brand, 'Tema ajeno', 'Consulta ajena');
        $this->persistedRun($user, $brand);
        $otherRun = $this->persistedRun($otherUser, $brand);
        $otherRun->update(['suggestions' => [
            $this->suggestion('Tema secreto', 'Consulta secreta')->toArray(),
            ...array_slice($otherRun->suggestions, 1),
        ]]);

        $context = app(StrategyContextBuilder::class)->build($brand, $user);
        $serialized = json_encode($context->toArray(), JSON_THROW_ON_ERROR);

        $this->assertSame('Tema propio', $context->recentDrafts[0]['title']);
        $this->assertSame('Consulta propia', $context->recentDrafts[0]['query']);
        $this->assertContains('Stickers resistentes al agua', $context->recentTopics);
        $this->assertStringNotContainsString('Tema ajeno', $serialized);
        $this->assertStringNotContainsString('Consulta ajena', $serialized);
        $this->assertStringNotContainsString('Tema secreto', $serialized);
    }

    public function test_strategy_generation_does_not_modify_approval_or_generation_history(): void
    {
        [$user, $brand] = $this->brand();
        $draft = $this->draftWithSnapshot($user, $brand, 'Aprobado', 'Consulta aprobada');
        $draft->update([
            'status' => Draft::STATUS_APPROVED,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);
        $generationRun = GenerationRun::query()->create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'context_snapshot_id' => $draft->contextSnapshot->getKey(),
            'user_id' => $user->getKey(),
            'provider' => 'fake',
            'model' => 'fake-mini',
            'operation' => 'draft_content',
            'status' => 'succeeded',
            'generated_content' => 'Contenido histórico',
            'evaluation_status' => 'requires_review',
            'grounding_status' => 'requires_review',
            'grounding_results' => [['status' => 'UNKNOWN']],
        ]);

        app(StrategyService::class)->generate($brand, $user);

        $this->assertSame(Draft::STATUS_APPROVED, $draft->fresh()->status);
        $this->assertSame('Contenido histórico', $generationRun->fresh()->generated_content);
        $this->assertSame('requires_review', $generationRun->evaluation_status);
        $this->assertSame('requires_review', $generationRun->grounding_status);
        $this->assertSame([['status' => 'UNKNOWN']], $generationRun->grounding_results);
        $this->assertSame(1, GenerationRun::query()->count());
        $this->assertSame(1, StrategyRun::query()->count());
    }

    public function test_strategy_page_displays_persisted_cards_without_generating_content(): void
    {
        [$user, $brand] = $this->brand();
        $run = $this->persistedRun($user, $brand);

        $this->get(route('marcas.estrategia.index', $brand))
            ->assertOk()
            ->assertSee('Stickers resistentes al agua')
            ->assertSee('Crear borrador')
            ->assertSee('no publicaciones ni contenido aprobado')
            ->assertSee(route('marcas.estrategia.borradores.store', [$brand, $run, 0]));

        $this->assertSame(0, Draft::query()->count());
        $this->assertSame(0, GenerationRun::query()->count());
        $this->assertSame(0, $this->calls);
    }

    /** @return array{User, Brand} */
    private function brand(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $this->actingAs($user);

        return [$user, $brand];
    }

    private function entry(
        Brand $brand,
        string $title,
        string $content,
        string $category,
        string $status = 'verified',
    ): KnowledgeEntry {
        return KnowledgeEntry::query()->create([
            'brand_id' => $brand->getKey(),
            'created_by' => auth()->id(),
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'status' => $status,
            'applicability' => 'global',
        ]);
    }

    private function strategyToken(Brand $brand): string
    {
        $response = $this->get(route('marcas.estrategia.index', $brand))->assertOk();
        preg_match('/name="strategy_token" value="([^"]+)"/', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    private function strategyResult(): StrategyResult
    {
        return new StrategyResult(
            $this->suggestions(),
            'fake',
            'fake-mini',
            100,
            20,
            50,
            'completed',
            'strategy_1',
        );
    }

    /** @return list<ContentSuggestion> */
    private function suggestions(): array
    {
        return [
            $this->suggestion('Stickers resistentes al agua', 'Creá un post breve sobre stickers resistentes al agua.'),
            new ContentSuggestion(
                'Identidad de marca',
                'brand_awareness',
                'instagram_story',
                'Presentar el enfoque personalizado de la marca.',
                'La identidad de marca está documentada.',
                'Creá una historia breve sobre la identidad de la marca.',
            ),
            new ContentSuggestion(
                'Proceso creativo',
                'educational',
                'instagram_post',
                'Explicar de forma general cómo nace un diseño.',
                'Permite educar sin introducir datos comerciales.',
                'Creá un post educativo sobre el proceso creativo general.',
            ),
        ];
    }

    private function suggestion(string $topic, string $query): ContentSuggestion
    {
        return new ContentSuggestion(
            $topic,
            'product_awareness',
            'instagram_post',
            'Mostrar un producto confirmado sin datos comerciales.',
            'El producto está respaldado por conocimiento verificado.',
            $query,
        );
    }

    /** @return array<string, mixed> */
    private function openAIResponse(?string $outputText = null): array
    {
        $outputText ??= json_encode([
            'suggestions' => collect($this->suggestions())
                ->map(fn (ContentSuggestion $suggestion): array => $suggestion->toArray())
                ->all(),
        ], JSON_THROW_ON_ERROR);

        return [
            'id' => 'resp_strategy',
            'model' => 'gpt-5-mini',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => $outputText,
                ]],
            ]],
            'usage' => [
                'input_tokens' => 100,
                'input_tokens_details' => ['cached_tokens' => 20],
                'output_tokens' => 50,
            ],
        ];
    }

    private function persistedRun(User $user, Brand $brand): StrategyRun
    {
        return StrategyRun::query()->create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'provider' => 'fake',
            'model' => 'fake-mini',
            'status' => 'succeeded',
            'context' => ['brand' => ['name' => $brand->name]],
            'suggestions' => collect($this->suggestions())
                ->map(fn (ContentSuggestion $suggestion): array => $suggestion->toArray())
                ->all(),
        ]);
    }

    private function draftWithSnapshot(User $user, Brand $brand, string $title, string $query): Draft
    {
        $draft = Draft::query()->create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => $title,
            'content' => 'Contenido',
            'status' => Draft::STATUS_DRAFT,
        ]);
        ContextSnapshot::query()->create([
            'draft_id' => $draft->getKey(),
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'query' => $query,
            'relevant_knowledge' => [],
            'brand_context' => [],
            'policies' => [],
            'restrictions' => [],
            'pending_knowledge' => [],
            'sources' => [],
            'warnings' => [],
            'missing_information' => [],
            'matches' => [],
        ]);

        return $draft->load('contextSnapshot');
    }

    private function useRealProvider(): void
    {
        $this->app->instance(StrategyProviderInterface::class, app(OpenAIStrategyProvider::class));
    }
}
