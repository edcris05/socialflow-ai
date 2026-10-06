<?php

namespace App\Services\Publishing;

final readonly class ScheduledPublishingResult
{
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_FAILED = 'failed';

    public const STATUS_OUTCOME_UNKNOWN = 'outcome_unknown';

    private function __construct(
        public string $status,
        public string $code,
        public string $message,
    ) {}

    public static function published(): self
    {
        return new self(self::STATUS_PUBLISHED, 'PUBLISHED', 'La publicación programada se completó.');
    }

    public static function skipped(string $code, string $message): self
    {
        return new self(self::STATUS_SKIPPED, $code, $message);
    }

    public static function blocked(string $code, string $message): self
    {
        return new self(self::STATUS_BLOCKED, $code, $message);
    }

    public static function failed(string $code, string $message): self
    {
        return new self(self::STATUS_FAILED, $code, $message);
    }

    public static function outcomeUnknown(string $code, string $message): self
    {
        return new self(self::STATUS_OUTCOME_UNKNOWN, $code, $message);
    }
}
