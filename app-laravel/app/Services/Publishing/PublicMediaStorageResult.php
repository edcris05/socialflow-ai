<?php

namespace App\Services\Publishing;

class PublicMediaStorageResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $provider = null,
        public readonly ?string $disk = null,
        public readonly ?string $objectKey = null,
        public readonly ?string $publicUrl = null,
        public readonly ?string $contentType = null,
        public readonly ?int $sizeBytes = null,
        public readonly ?string $checksumSha256 = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function succeeded(
        string $provider,
        string $disk,
        string $objectKey,
        string $publicUrl,
        string $contentType,
        int $sizeBytes,
        string $checksumSha256,
    ): self {
        return new self(
            successful: true,
            provider: $provider,
            disk: $disk,
            objectKey: $objectKey,
            publicUrl: $publicUrl,
            contentType: $contentType,
            sizeBytes: $sizeBytes,
            checksumSha256: $checksumSha256,
        );
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
