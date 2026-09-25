<?php

namespace App\Services\Knowledge;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;

class ContextBuilder
{
    public function __construct(private KnowledgeRetrieverInterface $retriever) {}

    public function build(Brand $brand, string $query): ContextPackage
    {
        $matches = $this->retriever->retrieve($brand, trim($query));
        $relevant = $matches
            ->map(fn (RetrievalMatch $match) => $match->entry)
            ->filter(fn ($entry): bool => $entry->status === 'verified')
            ->values();
        $pending = $matches
            ->map(fn (RetrievalMatch $match) => $match->entry)
            ->filter(fn ($entry): bool => $entry->status === 'pending')
            ->values();
        $brandContext = $brand->knowledgeEntries()
            ->where('status', 'verified')
            ->where('category', 'brand_identity')
            ->get();
        $applicableScopes = $this->applicableScopes($query, $relevant);
        $policies = $this->applicableRules($brand, 'policy', $applicableScopes);
        $restrictions = $this->applicableRules($brand, 'restriction', $applicableScopes);
        $sources = $relevant->concat($brandContext)->concat($policies)->concat($restrictions)->concat($pending)->pluck('source')->filter()->unique()->values();

        $warnings = $pending->map(fn ($entry): string => "Información pendiente: {$entry->title}.")->values()->all();
        if ($relevant->contains('category', 'price') && $policies->contains(fn (KnowledgeEntry $entry): bool => str_contains(mb_strtolower($entry->content), 'consultar') && str_contains(mb_strtolower($entry->content), 'precio'))) {
            $warnings[] = 'Precio antes de publicación: requiere confirmación.';
        }

        $stockEntry = $brand->knowledgeEntries()
            ->where('status', 'verified')
            ->where('title', 'like', '%Stock%')
            ->first();
        if ($stockEntry && $this->querySuggestsProductContent($query) && str_contains(mb_strtolower($stockEntry->content), 'no se encuentran documentadas')) {
            $warnings[] = 'Stock actual: requiere confirmación.';
        }
        $productionTimeEntry = $brand->knowledgeEntries()
            ->where('status', 'verified')
            ->where('title', 'like', '%Tiempos%')
            ->first();
        if ($productionTimeEntry && $this->querySuggestsProductContent($query) && str_contains(mb_strtolower($productionTimeEntry->content), 'no existen tiempos')) {
            $warnings[] = 'Tiempo de producción: requiere confirmación.';
        }

        $missing = [];
        if ($this->querySuggestsCommercialContent($query) && ! $relevant->contains('category', 'price')) {
            $missing[] = 'Precio: no hay un precio verificado recuperado.';
        }
        if ($this->querySuggestsProductContent($query) && ! $stockEntry) {
            $missing[] = 'Stock: disponibilidad actual desconocida.';
        }
        if ($this->querySuggestsProductContent($query) && ! $productionTimeEntry) {
            $missing[] = 'Tiempo de producción: no está documentado.';
        }

        return new ContextPackage(
            $brand,
            trim($query),
            $relevant,
            $brandContext,
            $policies,
            $restrictions,
            $pending,
            $sources,
            array_values(array_unique($warnings)),
            array_values(array_unique($missing)),
            $matches,
        );
    }

    private function applicableRules(Brand $brand, string $category, array $scopes): Collection
    {
        return $brand->knowledgeEntries()
            ->where('status', 'verified')
            ->where('category', $category)
            ->where(function ($query) use ($scopes): void {
                $query->where('applicability', 'global')->orWhereIn('applicability', $scopes);
            })
            ->get();
    }

    private function applicableScopes(string $query, Collection $relevant): array
    {
        $text = mb_strtolower($query);
        $scopes = ['content'];
        if ($relevant->contains('category', 'price') || str_contains($text, 'precio') || str_contains($text, 'public')) {
            $scopes[] = 'price';
        }
        if (preg_match('/ubicaci|direcci|retir|zona|env[ií]o|entrega/u', $text)) {
            $scopes[] = 'location';
            $scopes[] = 'delivery';
        }
        if (preg_match('/pedido|pago|anill|producci|personaliz/u', $text)) {
            $scopes[] = 'order';
        }
        if (preg_match('/reclamo|cambio|problema|devoluci/u', $text)) {
            $scopes[] = 'customer_service';
        }

        return array_values(array_unique($scopes));
    }

    private function querySuggestsCommercialContent(string $query): bool
    {
        return collect(['promocionar', 'publicar', 'vender', 'precio', 'producto', 'servicio', 'pedido'])
            ->contains(fn (string $term): bool => str_contains(mb_strtolower($query), $term));
    }

    private function querySuggestsProductContent(string $query): bool
    {
        return collect(['producto', 'sticker', 'etiqueta', 'impres', 'anill', 'servicio', 'pedido'])
            ->contains(fn (string $term): bool => str_contains(mb_strtolower($query), $term));
    }
}