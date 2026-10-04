<?php

namespace App\Services\Publishing;

final readonly class PublicationResult
{
    private function __construct(
        public bool $successful,
        public ?string $externalContainerId,
        public ?string $externalMediaId,
        public ?string $errorCode,
        public ?string $errorMessage,
        public bool $outcomeUncertain,
    ) {}

    public static function succeeded(
        ?string $externalContainerId = null,
        ?string $externalMediaId = null,
    ): self {
        return new self(true, $externalContainerId, $externalMediaId, null, null, false);
    }

    public static function failed(
        string $errorCode,
        string $errorMessage,
        bool $outcomeUncertain = false,
        ?string $externalContainerId = null,
    ): self {
        return new self(false, $externalContainerId, null, $errorCode, $errorMessage, $outcomeUncertain);
    }
}
