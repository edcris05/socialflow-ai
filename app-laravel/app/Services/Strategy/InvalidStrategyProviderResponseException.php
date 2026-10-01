<?php

namespace App\Services\Strategy;

use RuntimeException;

final class InvalidStrategyProviderResponseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $providerRequestId,
        public readonly ?int $inputTokens,
        public readonly ?int $cachedInputTokens,
        public readonly ?int $outputTokens,
        public readonly ?string $finishReason,
    ) {
        parent::__construct($message);
    }
}
