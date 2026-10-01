<?php

namespace App\Services\Strategy;

use App\Contracts\StrategyProviderInterface;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;
use Throwable;

class OpenAIStrategyProvider implements StrategyProviderInterface
{
    public function generate(StrategyContext $context): StrategyResult
    {
        $key = config('services.openai.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('OpenAI no está configurado.');
        }

        $response = Http::baseUrl('https://api.openai.com/v1')
            ->withToken($key)
            ->timeout((int) config('services.openai.timeout', 30))
            ->post('/responses', [
                'model' => config('services.openai.model'),
                'instructions' => $this->instructions(),
                'input' => json_encode($context->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'max_output_tokens' => (int) config('services.openai.strategy_max_output_tokens'),
                'reasoning' => ['effort' => 'minimal'],
                'store' => (bool) config('services.openai.store'),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'content_strategy_suggestions',
                        'strict' => true,
                        'schema' => $this->outputSchema(),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('El proveedor no pudo generar sugerencias.');
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];
        $outputText = $this->extractOutputText($data);
        $inputTokens = $this->nullableInt(data_get($data, 'usage.input_tokens'));
        $cachedInputTokens = $this->nullableInt(data_get($data, 'usage.input_tokens_details.cached_tokens'));
        $outputTokens = $this->nullableInt(data_get($data, 'usage.output_tokens'));
        $finishReason = $this->nullableString(data_get($data, 'status'));
        $providerRequestId = $this->nullableString(data_get($data, 'id'))
            ?? $this->nullableString($response->header('x-request-id'));

        if ($outputText === '' || $finishReason !== 'completed') {
            throw new InvalidStrategyProviderResponseException(
                $this->invalidContentMessage($response->status(), $data),
                $providerRequestId,
                $inputTokens,
                $cachedInputTokens,
                $outputTokens,
                $finishReason,
            );
        }

        try {
            $suggestions = $this->parseStructuredOutput($outputText);
        } catch (Throwable) {
            throw new InvalidStrategyProviderResponseException(
                'El proveedor devolvió sugerencias estructuradas inválidas.',
                $providerRequestId,
                $inputTokens,
                $cachedInputTokens,
                $outputTokens,
                $finishReason,
            );
        }

        return new StrategyResult(
            $suggestions,
            'openai',
            (string) data_get($data, 'model', config('services.openai.model')),
            $inputTokens,
            $cachedInputTokens,
            $outputTokens,
            $finishReason,
            $providerRequestId,
        );
    }

    private function instructions(): string
    {
        return <<<'INSTRUCTIONS'
Sos un asistente de estrategia de contenido. Proponé exactamente tres ideas distintas; no escribas el post final.

Usá únicamente brand y authorized_knowledge como hechos. Policies, restrictions y warnings tienen autoridad obligatoria. unusable_knowledge sólo identifica datos que NO podés presentar como hechos: nunca uses conocimiento pending ni future_idea como disponible.

No inventes productos, servicios, propiedades, usos, precios, promociones, descuentos, stock, disponibilidad ni plazos de producción o entrega. Sólo podés mencionarlos cuando estén explícitamente confirmados en authorized_knowledge y permitidos por policies/restrictions. generation_query debe respetar la misma regla.

No afirmes rendimiento, engagement esperado, mejores horarios ni resultados de analytics. Usá recent_drafts y recent_topics únicamente para evitar repetir exactamente temas recientes. Cada reason debe explicar la sugerencia con el contexto disponible, no con predicciones.

Objective debe pertenecer al catálogo del schema. Format sólo puede ser instagram_post o instagram_story. Devolvé exclusivamente el JSON estructurado solicitado.
INSTRUCTIONS;
    }

    /**
     * @return list<ContentSuggestion>
     *
     * @throws JsonException
     */
    private function parseStructuredOutput(string $outputText): array
    {
        $payload = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)
            || array_is_list($payload)
            || array_keys($payload) !== ['suggestions']
            || ! is_array($payload['suggestions'])
            || ! array_is_list($payload['suggestions'])
            || count($payload['suggestions']) !== 3) {
            throw new RuntimeException('Invalid strategy response shape.');
        }

        return collect($payload['suggestions'])
            ->map(function ($suggestion): ContentSuggestion {
                if (! is_array($suggestion) || array_is_list($suggestion)) {
                    throw new RuntimeException('Invalid strategy suggestion shape.');
                }

                return ContentSuggestion::fromArray($suggestion);
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'suggestions' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'topic' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
                            'objective' => ['type' => 'string', 'enum' => ContentSuggestion::OBJECTIVES],
                            'format' => ['type' => 'string', 'enum' => ContentSuggestion::FORMATS],
                            'angle' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                            'reason' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 700],
                            'generation_query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000],
                        ],
                        'required' => ['topic', 'objective', 'format', 'angle', 'reason', 'generation_query'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['suggestions'],
            'additionalProperties' => false,
        ];
    }

    /** @param array<string, mixed> $data */
    private function extractOutputText(array $data): string
    {
        $parts = [];

        foreach ($this->items(data_get($data, 'output')) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($this->items($item['content'] ?? null) as $content) {
                if (is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode('', $parts));
    }

    /** @param array<string, mixed> $data */
    private function invalidContentMessage(int $httpStatus, array $data): string
    {
        return sprintf(
            'El proveedor devolvió sugerencias inválidas. HTTP %d; response_status=%s; incomplete_reason=%s; usage=%s.',
            $httpStatus,
            $this->nullableString(data_get($data, 'status')) ?? 'missing',
            $this->nullableString(data_get($data, 'incomplete_details.reason')) ?? 'missing',
            data_get($data, 'usage') === null ? 'missing' : 'present',
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<int, mixed> */
    private function items(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
