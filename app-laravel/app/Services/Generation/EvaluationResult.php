<?php

namespace App\Services\Generation;

final readonly class EvaluationResult
{
    /**
     * @param  array<int, array{code: string, message: string, evidence: string}>  $violations
     * @param  array<int, array{code: string, message: string, evidence: string}>  $warnings
     */
    public function __construct(
        public string $status,
        public array $violations = [],
        public array $warnings = [],
    ) {}

    public static function passed(): self
    {
        return new self('passed');
    }

    public function requiresReview(): bool
    {
        return $this->status === 'requires_review';
    }
}
