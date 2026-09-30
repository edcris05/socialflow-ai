<?php

namespace App\Services\Knowledge\Grounding;

use App\Models\ContextSnapshot;

final class FactualGroundingEvaluator
{
    private const string AllowedUsePredicate = 'allowed_use';

    public function evaluate(FactualClaim $claim, ContextSnapshot $snapshot): GroundingEvaluationResult
    {
        $records = $this->recordsForSubject($snapshot, $claim->subject);

        if ($claim->predicate === self::AllowedUsePredicate) {
            return $this->evaluateAllowedUse($claim, $records);
        }

        $evidence = [];

        foreach ($records as $record) {
            foreach ($record['metadata']['claims'] ?? [] as $groundedClaim) {
                if (! is_array($groundedClaim)
                    || ($groundedClaim['predicate'] ?? null) !== $claim->predicate
                    || ! is_string($groundedClaim['value'] ?? null)) {
                    continue;
                }

                $evidence[] = [
                    'value' => $groundedClaim['value'],
                    'knowledge_entry_id' => $record['knowledge_entry_id'],
                    'evidence_excerpt' => is_string($groundedClaim['evidence_excerpt'] ?? null)
                        ? $groundedClaim['evidence_excerpt']
                        : null,
                    'phrase' => $this->firstPhrase($groundedClaim['phrases'] ?? null),
                    'source' => $record['source'],
                ];
            }
        }

        if ($evidence === []) {
            return $this->result(GroundingStatus::Unknown, $claim);
        }

        $values = array_values(array_unique(array_column($evidence, 'value')));
        sort($values);

        if (count($values) > 1) {
            return $this->result(GroundingStatus::Conflict, $claim, conflictingValues: $values);
        }

        $matches = array_values(array_filter(
            $evidence,
            fn (array $item): bool => $item['value'] === $claim->value,
        ));

        if ($matches === []) {
            return $this->result(GroundingStatus::Unknown, $claim);
        }

        $match = $this->firstDeterministically($matches);

        return $this->result(
            GroundingStatus::Supported,
            $claim,
            $match['knowledge_entry_id'],
            $match['evidence_excerpt'],
            $match['phrase'],
            $match['source'],
        );
    }

    /**
     * @param  list<array{knowledge_entry_id: string|null, source: string|null, metadata: array<string, mixed>}>  $records
     */
    private function evaluateAllowedUse(FactualClaim $claim, array $records): GroundingEvaluationResult
    {
        $supporting = [];
        $closed = [];
        $hasOpenCoverage = false;

        foreach ($records as $record) {
            $allowedUses = $record['metadata']['allowed_uses'] ?? null;
            if (! is_array($allowedUses) || ! is_array($allowedUses['values'] ?? null)) {
                continue;
            }

            if (in_array($claim->value, $allowedUses['values'], true)) {
                $supporting[] = $record;
            }

            if (($allowedUses['coverage'] ?? null) === 'open') {
                $hasOpenCoverage = true;
            }

            if (($allowedUses['coverage'] ?? null) === 'closed') {
                $closed[] = $record;
            }
        }

        if ($supporting !== []) {
            $match = $this->firstDeterministically($supporting);

            return $this->result(
                GroundingStatus::Supported,
                $claim,
                $match['knowledge_entry_id'],
                source: $match['source'],
            );
        }

        if ($closed !== [] && ! $hasOpenCoverage) {
            $match = $this->firstDeterministically($closed);

            return $this->result(
                GroundingStatus::Unsupported,
                $claim,
                $match['knowledge_entry_id'],
                source: $match['source'],
            );
        }

        return $this->result(GroundingStatus::Unknown, $claim);
    }

    /**
     * @return list<array{knowledge_entry_id: string|null, source: string|null, metadata: array<string, mixed>}>
     */
    private function recordsForSubject(ContextSnapshot $snapshot, string $subject): array
    {
        $records = [];

        foreach ($this->snapshotEntries($snapshot) as $entry) {
            $metadata = $entry['grounding_metadata'] ?? null;
            if (($entry['status'] ?? null) !== 'verified'
                || ! is_array($metadata)
                || ($metadata['subject'] ?? null) !== $subject) {
                continue;
            }

            $record = [
                'knowledge_entry_id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'source' => is_string($entry['source'] ?? null) ? $entry['source'] : null,
                'metadata' => $metadata,
            ];
            $records[$this->recordKey($record)] = $record;
        }

        return array_values($records);
    }

    /** @return list<array<string, mixed>> */
    private function snapshotEntries(ContextSnapshot $snapshot): array
    {
        $entries = [];

        foreach (['relevant_knowledge', 'brand_context', 'policies', 'restrictions'] as $attribute) {
            foreach ($snapshot->getAttribute($attribute) ?? [] as $entry) {
                if (is_array($entry)) {
                    $entries[] = $entry;
                }
            }
        }

        foreach ($snapshot->matches ?? [] as $match) {
            if (is_array($match) && is_array($match['entry'] ?? null)) {
                $entries[] = $match['entry'];
            }
        }

        return $entries;
    }

    /** @param array{knowledge_entry_id: string|null, source: string|null, metadata: array<string, mixed>} $record */
    private function recordKey(array $record): string
    {
        return implode('|', [
            $record['knowledge_entry_id'] ?? '',
            $record['source'] ?? '',
            json_encode($record['metadata'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function firstPhrase(mixed $phrases): ?string
    {
        if (! is_array($phrases)) {
            return null;
        }

        foreach ($phrases as $phrase) {
            if (is_string($phrase)) {
                return $phrase;
            }
        }

        return null;
    }

    /**
     * @template T of array{knowledge_entry_id: string|null, source: string|null}
     *
     * @param  non-empty-list<T>  $items
     * @return T
     */
    private function firstDeterministically(array $items): array
    {
        usort($items, fn (array $left, array $right): int => [
            $left['knowledge_entry_id'] ?? "\u{10FFFF}",
            $left['source'] ?? "\u{10FFFF}",
            $left['evidence_excerpt'] ?? "\u{10FFFF}",
            $left['phrase'] ?? "\u{10FFFF}",
        ] <=> [
            $right['knowledge_entry_id'] ?? "\u{10FFFF}",
            $right['source'] ?? "\u{10FFFF}",
            $right['evidence_excerpt'] ?? "\u{10FFFF}",
            $right['phrase'] ?? "\u{10FFFF}",
        ]);

        return $items[0];
    }

    /** @param list<string> $conflictingValues */
    private function result(
        GroundingStatus $status,
        FactualClaim $claim,
        ?string $knowledgeEntryId = null,
        ?string $evidenceExcerpt = null,
        ?string $phrase = null,
        ?string $source = null,
        array $conflictingValues = [],
    ): GroundingEvaluationResult {
        return new GroundingEvaluationResult(
            $status,
            $claim->subject,
            $claim->predicate,
            $claim->value,
            $knowledgeEntryId,
            $evidenceExcerpt,
            $phrase,
            $source,
            $conflictingValues,
        );
    }
}
