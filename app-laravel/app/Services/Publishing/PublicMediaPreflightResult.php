<?php

namespace App\Services\Publishing;

use Carbon\CarbonImmutable;

final readonly class PublicMediaPreflightResult
{
    private function __construct(
        public bool $passed,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?string $finalUrl,
        public ?string $contentType,
        public ?int $contentLength,
        public CarbonImmutable $checkedAt,
    ) {}

    public static function passed(
        string $finalUrl,
        string $contentType,
        ?int $contentLength,
    ): self {
        return new self(true, null, null, $finalUrl, $contentType, $contentLength, CarbonImmutable::now());
    }

    public static function failed(string $code, string $message): self
    {
        return new self(false, $code, $message, null, null, null, CarbonImmutable::now());
    }
}
