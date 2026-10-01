<?php

namespace App\Services\Strategy;

final readonly class StrategyResult
{
    /** @param list<ContentSuggestion> $suggestions */
    public function __construct(
        public array $suggestions,
        public string $provider,
        public string $model,
        public ?int $inputTokens,
        public ?int $cachedInputTokens,
        public ?int $outputTokens,
        public ?string $finishReason,
        public ?string $providerRequestId,
    ) {}
}
