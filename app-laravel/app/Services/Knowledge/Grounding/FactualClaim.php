<?php

namespace App\Services\Knowledge\Grounding;

use InvalidArgumentException;

final readonly class FactualClaim
{
    public function __construct(
        public string $subject,
        public string $predicate,
        public string $value,
    ) {
        $this->assertIdentifier($subject, 'subject');
        $this->assertIdentifier($predicate, 'predicate');
        $this->assertIdentifier($value, 'value');
    }

    private function assertIdentifier(string $value, string $field): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("Factual claim {$field} must be a stable snake_case identifier.");
        }
    }
}
