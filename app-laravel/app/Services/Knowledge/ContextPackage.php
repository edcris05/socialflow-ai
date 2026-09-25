<?php

namespace App\Services\Knowledge;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;

final readonly class ContextPackage
{
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

    /** @return array<string, mixed> */
    public function toSessionData(): array
    {
        return [
            'query' => $this->query,
            'relevant_knowledge' => $this->entries($this->relevantKnowledge),
            'brand_context' => $this->entries($this->brandContext),
            'policies' => $this->entries($this->policies),
            'restrictions' => $this->entries($this->restrictions),
            'pending_knowledge' => $this->entries($this->pendingKnowledge),
            'sources' => $this->sources->values()->all(),
            'warnings' => $this->warnings,
            'missing_information' => $this->missingInformation,
            'matches' => $this->matches->map(fn (RetrievalMatch $match): array => [
                'entry' => $this->entry($match->entry),
                'score' => $match->score,
                'matched_terms' => $match->matchedTerms,
            ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromSessionData(Brand $brand, array $data): self
    {
        return new self(
            $brand,
            (string) $data['query'],
            self::entriesFromData($data['relevant_knowledge'] ?? []),
            self::entriesFromData($data['brand_context'] ?? []),
            self::entriesFromData($data['policies'] ?? []),
            self::entriesFromData($data['restrictions'] ?? []),
            self::entriesFromData($data['pending_knowledge'] ?? []),
            collect($data['sources'] ?? []),
            $data['warnings'] ?? [],
            $data['missing_information'] ?? [],
            collect($data['matches'] ?? [])->map(fn (array $match): RetrievalMatch => new RetrievalMatch(
                self::entryFromData($match['entry']),
                (float) $match['score'],
                $match['matched_terms'] ?? [],
            )),
        );
    }

    private function entries(Collection $entries): array
    {
        return $entries->map(fn (KnowledgeEntry $entry): array => $this->entry($entry))->values()->all();
    }

    private function entry(KnowledgeEntry $entry): array
    {
        return $entry->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']);
    }

    private static function entriesFromData(array $entries): Collection
    {
        return collect($entries)->map(fn (array $entry): KnowledgeEntry => self::entryFromData($entry));
    }

    private static function entryFromData(array $entry): KnowledgeEntry
    {
        return (new KnowledgeEntry)->setRawAttributes($entry, true);
    }
}
