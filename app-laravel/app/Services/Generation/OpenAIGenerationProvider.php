<?php

namespace App\Services\Generation;

use App\Contracts\GenerationProviderInterface;
use App\Services\Prompting\GenerationPrompt;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;
use Throwable;

class OpenAIGenerationProvider implements GenerationProviderInterface
{
    public function generate(GenerationPrompt $prompt): GenerationResult
    {
        $key = config('services.openai.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('OpenAI no está configurado.');
        }

        $response = Http::baseUrl('https://api.openai.com/v1')->withToken($key)->timeout((int) config('services.openai.timeout', 30))->post('/responses', [
            'model' => config('services.openai.model'),
            'instructions' => $prompt->renderInstructions(),
            'input' => $prompt->renderInput(),
            'max_output_tokens' => (int) config('services.openai.max_output_tokens'),
            'reasoning' => ['effort' => 'minimal'],
            'store' => (bool) config('services.openai.store'),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'grounded_generation',
                    'strict' => true,
                    'schema' => $this->outputSchema(),
                ],
            ],
        ]);
        if ($response->failed()) {
            throw new RuntimeException('El proveedor no pudo generar contenido.');
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
            throw new InvalidProviderResponseException(
                $this->invalidContentMessage($response->status(), $data),
                $providerRequestId,
                $inputTokens,
                $cachedInputTokens,
                $outputTokens,
                $finishReason,
            );
        }

        try {
            [$content, $factualClaims] = $this->parseStructuredOutput($outputText);
        } catch (Throwable) {
            throw new InvalidProviderResponseException(
                'El proveedor devolvió output estructurado inválido.',
                $providerRequestId,
                $inputTokens,
                $cachedInputTokens,
                $outputTokens,
                $finishReason,
            );
        }

        return new GenerationResult(
            $content,
            'openai',
            (string) data_get($data, 'model', config('services.openai.model')),
            $inputTokens,
            $cachedInputTokens,
            $outputTokens,
            $finishReason,
            $providerRequestId,
            $factualClaims,
        );
    }

    /**
     * @return array{0: string, 1: list<DeclaredFactualClaim>}
     *
     * @throws JsonException
     */
    private function parseStructuredOutput(string $outputText): array
    {
        $payload = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)
            || array_is_list($payload)
            || ! $this->hasExactKeys($payload, ['content', 'factual_claims'])
            || ! is_string($payload['content'])
            || trim($payload['content']) === ''
            || ! is_array($payload['factual_claims'])
            || ! array_is_list($payload['factual_claims'])) {
            throw new RuntimeException('Invalid grounded generation shape.');
        }

        $claims = [];

        foreach ($payload['factual_claims'] as $claim) {
            if (! is_array($claim)
                || array_is_list($claim)
                || ! $this->hasExactKeys($claim, ['subject', 'predicate', 'value', 'text'])
                || ! is_string($claim['subject'])
                || ! is_string($claim['predicate'])
                || ! is_string($claim['value'])
                || ! is_string($claim['text'])) {
                throw new RuntimeException('Invalid declared factual claim shape.');
            }

            $declaredClaim = new DeclaredFactualClaim(
                $claim['subject'],
                $claim['predicate'],
                $claim['value'],
                $claim['text'],
            );

            if (! str_contains(
                $this->normalizeText($payload['content']),
                $this->normalizeText($declaredClaim->text),
            )) {
                throw new RuntimeException('Declared factual claim text is absent from content.');
            }

            $claims[] = $declaredClaim;
        }

        return [$payload['content'], $claims];
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $keys
     */
    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? '');
    }

    /** @return array<string, mixed> */
    private function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => ['type' => 'string'],
                'factual_claims' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'subject' => ['type' => 'string'],
                            'predicate' => ['type' => 'string'],
                            'value' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                        ],
                        'required' => ['subject', 'predicate', 'value', 'text'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['content', 'factual_claims'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractOutputText(array $data): string
    {
        $parts = [];

        foreach ($this->items(data_get($data, 'output')) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($this->items($item['content'] ?? null) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode('', $parts));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function invalidContentMessage(int $httpStatus, array $data): string
    {
        $itemTypes = [];
        $contentTypes = [];

        foreach ($this->items(data_get($data, 'output')) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (is_string($item['type'] ?? null)) {
                $itemTypes[] = $item['type'];
            }
            foreach ($this->items($item['content'] ?? null) as $content) {
                if (is_array($content) && is_string($content['type'] ?? null)) {
                    $contentTypes[] = $content['type'];
                }
            }
        }

        return sprintf(
            'El proveedor devolvió contenido inválido. HTTP %d; response_status=%s; incomplete_reason=%s; output_types=%s; content_types=%s; usage=%s.',
            $httpStatus,
            $this->nullableString(data_get($data, 'status')) ?? 'missing',
            $this->nullableString(data_get($data, 'incomplete_details.reason')) ?? 'missing',
            implode(',', array_unique($itemTypes)) ?: 'missing',
            implode(',', array_unique($contentTypes)) ?: 'missing',
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

    /**
     * @return array<int, mixed>
     */
    private function items(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
