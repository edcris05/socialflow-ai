<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeEntry;

final readonly class RetrievalMatch
{
    /** @param list<string> $matchedTerms */
    public function __construct(
        public KnowledgeEntry $entry,
        public float $score,
        public array $matchedTerms,
    ) {}
}