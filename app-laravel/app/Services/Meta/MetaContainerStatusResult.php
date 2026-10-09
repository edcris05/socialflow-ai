<?php

namespace App\Services\Meta;

final readonly class MetaContainerStatusResult
{
    private function __construct(
        public bool $successful,
        public ?MetaContainerStatus $status,
        public ?string $errorCode,
        public ?string $errorMessage,
    ) {}

    public static function succeeded(MetaContainerStatus $status): self
    {
        return new self(true, $status, null, null);
    }

    public static function failed(string $errorCode, string $errorMessage): self
    {
        return new self(false, null, $errorCode, $errorMessage);
    }
}
