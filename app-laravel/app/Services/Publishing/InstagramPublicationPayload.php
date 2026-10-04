<?php

namespace App\Services\Publishing;

final readonly class InstagramPublicationPayload
{
    public function __construct(
        public string $caption,
        public ?string $imageUrl,
    ) {}
}
