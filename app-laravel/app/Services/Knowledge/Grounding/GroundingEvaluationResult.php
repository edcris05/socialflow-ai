<?php

namespace App\Services\Knowledge\Grounding;

final readonly class GroundingEvaluationResult
{
    /** @param list<string> $conflictingValues */
    public function __construct(
        public GroundingStatus $status,
        public string $subject,
        public string $predicate,
        public string $value,
        public ?string $knowledgeEntryId = null,
        public ?string $evidenceExcerpt = null,
        public ?string $phrase = null,
        public ?string $source = null,
        public array $conflictingValues = [],
    ) {}

    /**
     * @return array{
     *     status: string,
     *     subject: string,
     *     predicate: string,
     *     value: string,
     *     knowledge_entry_id: string|null,
     *     evidence_excerpt: string|null,
     *     phrase: string|null,
     *     source: string|null,
     *     conflicting_values: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'subject' => $this->subject,
            'predicate' => $this->predicate,
            'value' => $this->value,
            'knowledge_entry_id' => $this->knowledgeEntryId,
            'evidence_excerpt' => $this->evidenceExcerpt,
            'phrase' => $this->phrase,
            'source' => $this->source,
            'conflicting_values' => $this->conflictingValues,
        ];
    }
}
