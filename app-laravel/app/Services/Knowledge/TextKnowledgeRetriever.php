<?php

namespace App\Services\Knowledge;

use App\Models\Brand;
use Illuminate\Support\Collection;
use App\Services\Knowledge\KnowledgeRetrieverInterface;

class TextKnowledgeRetriever implements KnowledgeRetrieverInterface
{
    public function __construct(private int $limit = 8) {}

    /** @return Collection<int, RetrievalMatch> */
    public function retrieve(Brand $brand, string $query): Collection
    {
        $terms = $this->terms($query);
        if ($terms->isEmpty()) {
            return collect();
        }

        $productTerms = $this->productTerms($brand);

        return $brand->knowledgeEntries()
            ->whereIn('status', ['verified', 'pending'])
            ->get()
            ->map(fn ($entry): ?RetrievalMatch => $this->score($entry, $terms, $query))
            ->filter()
            ->reject(fn (RetrievalMatch $match): bool => $this->isCompetingProduct($match->entry, $terms, $productTerms))
            ->reject(fn (RetrievalMatch $match): bool => $match->entry->category === 'future_idea' && ! $this->isFutureQuery($query))
            ->sortByDesc('score')
            ->take($this->limit)
            ->values();
    }

    private function score(object $entry, Collection $terms, string $query): ?RetrievalMatch
    {
        $title = mb_strtolower($entry->title);
        $content = mb_strtolower($entry->content);
        $source = mb_strtolower((string) $entry->source);
        $matched = [];
        $score = 0.0;

        foreach ($terms as $term) {
            $titleHits = substr_count($title, $term);
            $contentHits = substr_count($content, $term);
            $sourceHits = substr_count($source, $term);
            if ($titleHits + $contentHits + $sourceHits > 0) {
                $matched[] = $term;
                $score += ($titleHits * 5) + ($contentHits * 2) + ($sourceHits * 0.5);
            }
        }

        if ($matched === []) {
            return null;
        }

        if (str_contains($title, mb_strtolower(trim($query)))) {
            $score += 4;
        }
        if ($entry->status === 'pending') {
            $score -= 1;
        }

        return new RetrievalMatch($entry, round($score, 2), array_values(array_unique($matched)));
    }

    private function terms(string $query): Collection
    {
        $stopWords = ['quiero', 'una', 'uno', 'para', 'con', 'los', 'las', 'del', 'que', 'como', 'por', 'al'];

        return collect(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [])
            ->map(fn (string $term): string => preg_replace('/[^\pL\pN]+/u', '', $term) ?? '')
            ->reject(fn (string $term): bool => mb_strlen($term) < 3 || in_array($term, $stopWords, true))
            ->unique()
            ->values();
    }

    private function isFutureQuery(string $query): bool
    {
        $query = mb_strtolower($query);

        return str_contains($query, 'futur')
            || str_contains($query, 'idea')
            || str_contains($query, 'brainstorm')
            || str_contains($query, 'planificar');
    }

    private function productTerms(Brand $brand): Collection
    {
        $generic = [
            'producto', 'productos', 'servicio', 'servicios', 'precio', 'precios',
            'personalizado', 'personalizados', 'ofrecido', 'ofrecidos', 'ofrecida',
            'ofrecidas', 'disponible', 'disponibles', 'produccion', 'producción',
        ];

        return $brand->knowledgeEntries()
            ->whereIn('category', ['product', 'price'])
            ->pluck('title')
            ->flatMap(fn (string $title) => $this->terms($title))
            ->reject(fn (string $term): bool => in_array($term, $generic, true))
            ->unique()
            ->values();
    }

    private function isCompetingProduct(object $entry, Collection $queryTerms, Collection $productTerms): bool
    {
        if (! in_array($entry->category, ['product', 'price'], true)) {
            return false;
        }

        $titleTerms = $this->terms($entry->title)->intersect($productTerms);
        $queryProducts = $queryTerms->intersect($productTerms);

        return $queryProducts->isNotEmpty()
            && $titleTerms->isNotEmpty()
            && $titleTerms->intersect($queryProducts)->isEmpty();
    }
}