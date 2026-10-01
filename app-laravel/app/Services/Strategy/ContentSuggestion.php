<?php

namespace App\Services\Strategy;

use InvalidArgumentException;

final readonly class ContentSuggestion
{
    public const FORMATS = [
        'instagram_post',
        'instagram_story',
    ];

    public const OBJECTIVES = [
        'product_awareness',
        'service_awareness',
        'brand_awareness',
        'engagement',
        'educational',
    ];

    public function __construct(
        public string $topic,
        public string $objective,
        public string $format,
        public string $angle,
        public string $reason,
        public string $generationQuery,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        $expectedKeys = ['angle', 'format', 'generation_query', 'objective', 'reason', 'topic'];
        sort($keys);

        if ($keys !== $expectedKeys) {
            throw new InvalidArgumentException('Invalid content suggestion keys.');
        }

        foreach ($expectedKeys as $key) {
            if (! is_string($data[$key]) || trim($data[$key]) === '') {
                throw new InvalidArgumentException('Invalid content suggestion value.');
            }
        }

        if (! in_array($data['objective'], self::OBJECTIVES, true)
            || ! in_array($data['format'], self::FORMATS, true)
            || mb_strlen($data['topic']) > 160
            || mb_strlen($data['angle']) > 500
            || mb_strlen($data['reason']) > 700
            || mb_strlen($data['generation_query']) > 2000) {
            throw new InvalidArgumentException('Invalid content suggestion constraints.');
        }

        return new self(
            trim($data['topic']),
            $data['objective'],
            $data['format'],
            trim($data['angle']),
            trim($data['reason']),
            trim($data['generation_query']),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'objective' => $this->objective,
            'format' => $this->format,
            'angle' => $this->angle,
            'reason' => $this->reason,
            'generation_query' => $this->generationQuery,
        ];
    }
}
