<?php

namespace App\Models;

use App\Services\Knowledge\ContextPackage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'draft_id',
    'brand_id',
    'user_id',
    'query',
    'relevant_knowledge',
    'brand_context',
    'policies',
    'restrictions',
    'pending_knowledge',
    'sources',
    'warnings',
    'missing_information',
    'matches',
])]
class ContextSnapshot extends Model
{
    use HasUlids;

    public static function fromPackage(Draft $draft, User $user, ContextPackage $package): self
    {
        return new self([
            'draft_id' => $draft->getKey(),
            'brand_id' => $package->brand->getKey(),
            'user_id' => $user->getKey(),
            'query' => $package->query,
            'relevant_knowledge' => self::entries($package->relevantKnowledge),
            'brand_context' => self::entries($package->brandContext),
            'policies' => self::entries($package->policies),
            'restrictions' => self::entries($package->restrictions),
            'pending_knowledge' => self::entries($package->pendingKnowledge),
            'sources' => $package->sources->values()->all(),
            'warnings' => $package->warnings,
            'missing_information' => $package->missingInformation,
            'matches' => $package->matches
                ->map(fn ($match): array => [
                    'entry' => self::entry($match->entry),
                    'score' => $match->score,
                    'matched_terms' => $match->matchedTerms,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function toPackage(): ContextPackage
    {
        return new ContextPackage(
            $this->brand,
            (string) $this->query,
            collect($this->relevant_knowledge ?? []),
            collect($this->brand_context ?? []),
            collect($this->policies ?? []),
            collect($this->restrictions ?? []),
            collect($this->pending_knowledge ?? []),
            collect($this->sources ?? []),
            $this->warnings ?? [],
            $this->missing_information ?? [],
            collect($this->matches ?? []),
        );
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'relevant_knowledge' => 'array',
            'brand_context' => 'array',
            'policies' => 'array',
            'restrictions' => 'array',
            'pending_knowledge' => 'array',
            'sources' => 'array',
            'warnings' => 'array',
            'missing_information' => 'array',
            'matches' => 'array',
        ];
    }

    private static function entries(Collection $entries): array
    {
        return $entries
            ->map(fn (KnowledgeEntry $entry): array => self::entry($entry))
            ->values()
            ->all();
    }

    private static function entry(KnowledgeEntry $entry): array
    {
        return $entry->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']);
    }
}