<?php

namespace App\Services\Knowledge;

use App\Models\Brand;
use Illuminate\Support\Collection;

final readonly class ContextPackage
{
    /**
     * @param Collection<int, \App\Models\KnowledgeEntry> $relevantKnowledge
     * @param Collection<int, \App\Models\KnowledgeEntry> $brandContext
     * @param Collection<int, \App\Services\Knowledge\RetrievalMatch> $matches
     */
    public function __construct(
        public Brand $brand,
        public string $query,
        public Collection $relevantKnowledge,
        public Collection $brandContext,
        public Collection $policies,
        public Collection $restrictions,
        public Collection $pendingKnowledge,
        public Collection $sources,
        public array $warnings,
        public array $missingInformation,
        public Collection $matches,
    ) {}
}