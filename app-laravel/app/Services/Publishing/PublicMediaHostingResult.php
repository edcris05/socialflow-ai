<?php

namespace App\Services\Publishing;

use App\Models\PublicMediaHosting;

class PublicMediaHostingResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?PublicMediaHosting $hosting = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function succeeded(PublicMediaHosting $hosting): self
    {
        return new self(successful: true, hosting: $hosting);
    }

    public static function failed(string $code, string $message): self
    {
        return new self(
            successful: false,
            errorCode: $code,
            errorMessage: $message,
        );
    }
}
