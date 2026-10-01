<?php

namespace App\Services\Strategy;

final readonly class StrategyContext
{
    public function __construct(
        public array $brand,
        public array $authorizedKnowledge,
        public array $policies,
        public array $restrictions,
        public array $unusableKnowledge,
        public array $warnings,
        public array $recentDrafts,
        public array $recentTopics,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'authorized_knowledge' => $this->authorizedKnowledge,
            'policies' => $this->policies,
            'restrictions' => $this->restrictions,
            'unusable_knowledge' => $this->unusableKnowledge,
            'warnings' => $this->warnings,
            'recent_drafts' => $this->recentDrafts,
            'recent_topics' => $this->recentTopics,
        ];
    }
}
