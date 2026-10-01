<?php

namespace App\Services\Strategy;

use App\Contracts\StrategyProviderInterface;
use App\Models\Brand;
use App\Models\StrategyRun;
use App\Models\User;
use RuntimeException;
use Throwable;

class StrategyService
{
    public function __construct(
        private StrategyProviderInterface $provider,
        private StrategyContextBuilder $contextBuilder,
    ) {}

    public function generate(Brand $brand, User $user): StrategyRun
    {
        $context = $this->contextBuilder->build($brand, $user);
        $run = StrategyRun::query()->create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'provider' => 'openai',
            'model' => (string) config('services.openai.model'),
            'status' => 'pending',
            'context' => $context->toArray(),
        ]);
        $run->update(['status' => 'running']);

        try {
            $result = $this->provider->generate($context);
            if (count($result->suggestions) !== 3
                || ! array_is_list($result->suggestions)
                || collect($result->suggestions)->contains(fn ($suggestion): bool => ! $suggestion instanceof ContentSuggestion)) {
                throw new RuntimeException('El proveedor devolvió sugerencias inválidas.');
            }

            $run->update([
                'status' => 'succeeded',
                'suggestions' => collect($result->suggestions)
                    ->map(fn (ContentSuggestion $suggestion): array => $suggestion->toArray())
                    ->all(),
                'provider' => $result->provider,
                'model' => $result->model,
                'input_tokens' => $result->inputTokens,
                'cached_input_tokens' => $result->cachedInputTokens,
                'output_tokens' => $result->outputTokens,
                'estimated_cost_usd' => $this->estimatedCost(
                    $result->inputTokens,
                    $result->cachedInputTokens,
                    $result->outputTokens,
                ),
                'provider_request_id' => $result->providerRequestId,
                'finish_reason' => $result->finishReason,
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $failure = [
                'status' => 'failed',
                'suggestions' => null,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ];

            if ($exception instanceof InvalidStrategyProviderResponseException) {
                $failure += [
                    'input_tokens' => $exception->inputTokens,
                    'cached_input_tokens' => $exception->cachedInputTokens,
                    'output_tokens' => $exception->outputTokens,
                    'estimated_cost_usd' => $this->estimatedCost(
                        $exception->inputTokens,
                        $exception->cachedInputTokens,
                        $exception->outputTokens,
                    ),
                    'provider_request_id' => $exception->providerRequestId,
                    'finish_reason' => $exception->finishReason,
                ];
            }

            $run->update($failure);
        }

        return $run->fresh();
    }

    private function estimatedCost(?int $inputTokens, ?int $cachedInputTokens, ?int $outputTokens): ?float
    {
        if ($inputTokens === null || $outputTokens === null) {
            return null;
        }

        $prices = config('services.openai.pricing');
        if (! is_array($prices)
            || ! is_numeric($prices['input'] ?? null)
            || ! is_numeric($prices['cached_input'] ?? null)
            || ! is_numeric($prices['output'] ?? null)) {
            return null;
        }

        $cached = $cachedInputTokens ?? 0;
        if ($cached > $inputTokens) {
            return null;
        }

        return (($inputTokens - $cached) * (float) $prices['input']
            + $cached * (float) $prices['cached_input']
            + $outputTokens * (float) $prices['output']) / 1000000;
    }
}
