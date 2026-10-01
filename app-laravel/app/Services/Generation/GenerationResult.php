<?php

namespace App\Services\Generation;

final readonly class GenerationResult
{
    /**
     * @param  list<DeclaredFactualClaim>  $factualClaims
     */
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public ?int $inputTokens,
        public ?int $cachedInputTokens,
        public ?int $outputTokens,
        public ?string $finishReason,
        public ?string $providerRequestId,
        public array $factualClaims = [],
    ) {}
}
