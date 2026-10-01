<?php

namespace App\Services\Strategy;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use App\Models\StrategyRun;
use App\Models\User;
use Illuminate\Support\Collection;

class StrategyContextBuilder
{
    public function build(Brand $brand, User $user): StrategyContext
    {
        $verified = $brand->knowledgeEntries()
            ->where('status', 'verified')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $authorizedKnowledge = $verified
            ->reject(fn (KnowledgeEntry $entry): bool => in_array($entry->category, ['policy', 'restriction', 'future_idea'], true))
            ->take(30)
            ->map(fn (KnowledgeEntry $entry): array => $this->authorizedEntry($entry))
            ->values()
            ->all();
        $policies = $verified
            ->where('category', 'policy')
            ->take(20)
            ->map(fn (KnowledgeEntry $entry): array => $this->authorizedEntry($entry))
            ->values()
            ->all();
        $restrictions = $verified
            ->where('category', 'restriction')
            ->take(20)
            ->map(fn (KnowledgeEntry $entry): array => $this->authorizedEntry($entry))
            ->values()
            ->all();
        $unusable = $brand->knowledgeEntries()
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere(function ($future): void {
                        $future->where('status', 'verified')->where('category', 'future_idea');
                    });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'title', 'category', 'status']);
        $unusableKnowledge = $unusable
            ->map(fn (KnowledgeEntry $entry): array => [
                'knowledge_entry_id' => (string) $entry->getKey(),
                'title' => $entry->title,
                'category' => $entry->category,
                'status' => $entry->status,
                'reason' => $entry->status === 'pending'
                    ? 'pending_not_authorized'
                    : 'future_idea_not_available',
            ])
            ->values()
            ->all();

        return new StrategyContext(
            [
                'name' => $brand->name,
                'description' => $brand->description,
                'tone_of_voice' => $brand->tone_of_voice,
                'target_audience' => $brand->target_audience,
                'social_channels' => $brand->social_channels ?? [],
                'restrictions' => $brand->restrictions ?? [],
            ],
            $authorizedKnowledge,
            $policies,
            $restrictions,
            $unusableKnowledge,
            $this->warnings($unusable),
            $this->recentDrafts($brand, $user),
            $this->recentTopics($brand, $user),
        );
    }

    /** @return array<string, mixed> */
    private function authorizedEntry(KnowledgeEntry $entry): array
    {
        return [
            'knowledge_entry_id' => (string) $entry->getKey(),
            'title' => $entry->title,
            'content' => $entry->content,
            'category' => $entry->category,
            'source' => $entry->source,
            'applicability' => $entry->applicability,
        ];
    }

    /** @return list<string> */
    private function warnings(Collection $unusable): array
    {
        return $unusable
            ->map(fn (KnowledgeEntry $entry): string => $entry->status === 'pending'
                ? "No utilizar como hecho: {$entry->title} está pendiente de verificación."
                : "No presentar como disponible: {$entry->title} es una idea futura.")
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentDrafts(Brand $brand, User $user): array
    {
        return $brand->drafts()
            ->whereBelongsTo($user)
            ->with('contextSnapshot:id,draft_id,query')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(5)
            ->get(['id', 'title', 'status', 'created_at'])
            ->map(fn ($draft): array => [
                'title' => $draft->title,
                'query' => $draft->contextSnapshot?->query,
                'status' => $draft->status,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function recentTopics(Brand $brand, User $user): array
    {
        return StrategyRun::query()
            ->whereBelongsTo($brand)
            ->whereBelongsTo($user)
            ->where('status', 'succeeded')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(3)
            ->get(['suggestions'])
            ->flatMap(fn (StrategyRun $run): array => collect($run->suggestions ?? [])
                ->pluck('topic')
                ->filter(fn ($topic): bool => is_string($topic) && $topic !== '')
                ->all())
            ->take(9)
            ->values()
            ->all();
    }
}
