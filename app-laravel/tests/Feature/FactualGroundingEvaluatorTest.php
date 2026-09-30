<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Knowledge\ContextPackage;
use App\Services\Knowledge\Grounding\FactualClaim;
use App\Services\Knowledge\Grounding\FactualGroundingEvaluator;
use App\Services\Knowledge\Grounding\GroundingEvaluationResult;
use App\Services\Knowledge\Grounding\GroundingStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FactualGroundingEvaluatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FactualGroundingEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new FactualGroundingEvaluator;
    }

    public function test_exact_structured_claim_is_supported_with_historical_evidence(): void
    {
        $result = $this->evaluate(
            new FactualClaim('stickers', 'water_resistance', 'resistant'),
            [$this->entry('entry-a', $this->metadata())],
        );

        $this->assertSame(GroundingStatus::Supported, $result->status);
        $this->assertSame('entry-a', $result->knowledgeEntryId);
        $this->assertSame('Adhesivo resistente al agua', $result->evidenceExcerpt);
        $this->assertSame('resistentes al agua', $result->phrase);
        $this->assertSame('Fuente confirmada', $result->source);
    }

    public function test_waterproof_is_unknown_when_snapshot_only_supports_resistant(): void
    {
        $result = $this->evaluate(
            new FactualClaim('stickers', 'water_resistance', 'waterproof'),
            [$this->entry('entry-a', $this->metadata())],
        );

        $this->assertSame(GroundingStatus::Unknown, $result->status);
        $this->assertNull($result->knowledgeEntryId);
        $this->assertNull($result->evidenceExcerpt);
    }

    public function test_unrepresented_predicate_is_unknown(): void
    {
        $result = $this->evaluate(
            new FactualClaim('stickers', 'durability', 'durable'),
            [$this->entry('entry-a', $this->metadata())],
        );

        $this->assertSame(GroundingStatus::Unknown, $result->status);
    }

    public function test_absent_allowed_use_with_open_coverage_is_unknown(): void
    {
        $result = $this->evaluate(
            new FactualClaim('stickers', 'allowed_use', 'bottles'),
            [$this->entry('entry-a', $this->metadata(coverage: 'open'))],
        );

        $this->assertSame(GroundingStatus::Unknown, $result->status);
    }

    public function test_absent_allowed_use_with_closed_coverage_is_unsupported(): void
    {
        $result = $this->evaluate(
            new FactualClaim('stickers', 'allowed_use', 'bottles'),
            [$this->entry('entry-a', $this->metadata(coverage: 'closed'))],
        );

        $this->assertSame(GroundingStatus::Unsupported, $result->status);
        $this->assertSame('entry-a', $result->knowledgeEntryId);
        $this->assertNull($result->evidenceExcerpt);
    }

    public function test_listed_allowed_use_is_supported(): void
    {
        $result = $this->evaluate(
            new FactualClaim('stickers', 'allowed_use', 'bottles'),
            [$this->entry('entry-a', $this->metadata(['bottles'], 'closed'))],
        );

        $this->assertSame(GroundingStatus::Supported, $result->status);
        $this->assertSame('entry-a', $result->knowledgeEntryId);
        $this->assertNull($result->evidenceExcerpt);
    }

    public function test_entry_without_grounding_metadata_is_unknown(): void
    {
        $result = $this->evaluate(
            new FactualClaim('stickers', 'water_resistance', 'resistant'),
            [$this->entry('legacy-entry')],
        );

        $this->assertSame(GroundingStatus::Unknown, $result->status);
    }

    public function test_evaluation_uses_historical_snapshot_after_current_entry_changes(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $entry = new KnowledgeEntry([
            'title' => 'Stickers resistentes',
            'content' => 'Adhesivo resistente al agua.',
            'status' => 'verified',
            'grounding_metadata' => $this->metadata(),
        ]);
        $entry->brand()->associate($brand);
        $entry->created_by = $user->id;
        $entry->save();
        $draft = new Draft(['title' => 'Draft', 'content' => '', 'status' => 'draft']);
        $draft->brand()->associate($brand);
        $draft->user()->associate($user);
        $draft->save();
        $snapshot = ContextSnapshot::fromPackage($draft, $user, new ContextPackage(
            $brand,
            'Promocionar stickers',
            collect([$entry]),
            collect(),
            collect(),
            collect(),
            collect(),
            collect(),
            [],
            [],
            collect(),
        ));
        $snapshot->save();

        $entry->update([
            'content' => 'Stickers con acabado mate.',
            'grounding_metadata' => $this->metadata(
                predicate: 'finish',
                value: 'matte',
                phrase: 'acabado mate',
                evidenceExcerpt: 'acabado mate',
            ),
        ]);

        $historical = $this->evaluator->evaluate(
            new FactualClaim('stickers', 'water_resistance', 'resistant'),
            $snapshot->fresh(),
        );
        $current = $this->evaluator->evaluate(
            new FactualClaim('stickers', 'finish', 'matte'),
            $snapshot->fresh(),
        );

        $this->assertSame(GroundingStatus::Supported, $historical->status);
        $this->assertSame(GroundingStatus::Unknown, $current->status);
        $this->assertSame('Adhesivo resistente al agua', $historical->evidenceExcerpt);
    }

    public function test_evidence_cannot_leak_between_brand_snapshots(): void
    {
        $brandASnapshot = $this->snapshot([$this->entry('brand-a-entry')]);
        $brandBSnapshot = $this->snapshot([$this->entry('brand-b-entry', $this->metadata())]);
        $claim = new FactualClaim('stickers', 'water_resistance', 'resistant');

        $this->assertSame(GroundingStatus::Unknown, $this->evaluator->evaluate($claim, $brandASnapshot)->status);
        $this->assertSame(GroundingStatus::Supported, $this->evaluator->evaluate($claim, $brandBSnapshot)->status);
    }

    public function test_same_historical_entry_repeated_across_snapshot_sections_is_deduplicated(): void
    {
        $entry = $this->entry('entry-a', $this->metadata());
        $snapshot = new ContextSnapshot([
            'relevant_knowledge' => [$entry],
            'brand_context' => [$entry],
            'policies' => [$entry],
            'restrictions' => [$entry],
            'pending_knowledge' => [],
            'matches' => [[
                'entry' => $entry,
                'score' => 1.0,
                'matched_terms' => ['stickers'],
            ]],
        ]);

        $result = $this->evaluator->evaluate(
            new FactualClaim('stickers', 'water_resistance', 'resistant'),
            $snapshot,
        );

        $this->assertSame(GroundingStatus::Supported, $result->status);
        $this->assertSame('entry-a', $result->knowledgeEntryId);
        $this->assertSame('Adhesivo resistente al agua', $result->evidenceExcerpt);
        $this->assertSame('resistentes al agua', $result->phrase);
        $this->assertSame([], $result->conflictingValues);
    }

    public function test_equivalent_evidence_is_selected_deterministically(): void
    {
        $later = $this->entry('entry-b', $this->metadata(), 'Fuente B');
        $earlier = $this->entry('entry-a', $this->metadata(), 'Fuente A');
        $claim = new FactualClaim('stickers', 'water_resistance', 'resistant');

        $first = $this->evaluate($claim, [$later, $earlier]);
        $second = $this->evaluate($claim, [$earlier, $later]);

        $this->assertSame(GroundingStatus::Supported, $first->status);
        $this->assertSame('entry-a', $first->knowledgeEntryId);
        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_distinct_explicit_values_for_same_subject_and_predicate_are_a_conflict(): void
    {
        $waterproof = $this->metadata(value: 'waterproof', phrase: 'impermeables', evidenceExcerpt: 'Material impermeable');
        $entries = [
            $this->entry('entry-a', $this->metadata()),
            $this->entry('entry-b', $waterproof),
        ];

        $result = $this->evaluate(
            new FactualClaim('stickers', 'water_resistance', 'resistant'),
            $entries,
        );

        $this->assertSame(GroundingStatus::Conflict, $result->status);
        $this->assertSame(['resistant', 'waterproof'], $result->conflictingValues);
        $this->assertNull($result->knowledgeEntryId);
    }

    /** @param list<array<string, mixed>> $entries */
    private function evaluate(FactualClaim $claim, array $entries): GroundingEvaluationResult
    {
        return $this->evaluator->evaluate($claim, $this->snapshot($entries));
    }

    /** @param list<array<string, mixed>> $entries */
    private function snapshot(array $entries): ContextSnapshot
    {
        return new ContextSnapshot([
            'relevant_knowledge' => $entries,
            'brand_context' => [],
            'policies' => [],
            'restrictions' => [],
            'pending_knowledge' => [],
            'matches' => [],
        ]);
    }

    /** @return array<string, mixed> */
    private function entry(string $id, ?array $metadata = null, string $source = 'Fuente confirmada'): array
    {
        return [
            'id' => $id,
            'title' => 'Stickers',
            'content' => 'Adhesivo resistente al agua.',
            'status' => 'verified',
            'source' => $source,
            'grounding_metadata' => $metadata,
        ];
    }

    /** @return array<string, mixed> */
    private function metadata(
        array $allowedUses = [],
        string $coverage = 'open',
        string $predicate = 'water_resistance',
        string $value = 'resistant',
        string $phrase = 'resistentes al agua',
        string $evidenceExcerpt = 'Adhesivo resistente al agua',
    ): array {
        return [
            'subject' => 'stickers',
            'claims' => [[
                'predicate' => $predicate,
                'value' => $value,
                'phrases' => [$phrase],
                'evidence_excerpt' => $evidenceExcerpt,
            ]],
            'allowed_uses' => [
                'values' => $allowedUses,
                'coverage' => $coverage,
            ],
        ];
    }
}
