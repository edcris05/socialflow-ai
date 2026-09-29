<?php

namespace App\Services\Generation;

use App\Contracts\GenerationEvaluatorInterface;
use App\Models\ContextSnapshot;

class DeterministicGenerationEvaluator implements GenerationEvaluatorInterface
{
    public function evaluate(string $content, ContextSnapshot $snapshot): EvaluationResult
    {
        $violations = [];

        if ($this->requiresConfirmation($snapshot, 'precio') && $this->mentionsPrice($content)) {
            $violations[] = $this->violation(
                'PRICE_REQUIRES_CONFIRMATION',
                'El contenido menciona un precio pero el contexto requiere confirmarlo.',
                'warning: precio requiere confirmacion',
            );
        }

        if ($this->requiresConfirmation($snapshot, 'stock') && $this->claimsStock($content)) {
            $violations[] = $this->violation(
                'STOCK_REQUIRES_CONFIRMATION',
                'El contenido afirma disponibilidad o stock pero el contexto requiere confirmarlo.',
                'warning: stock requiere confirmacion',
            );
        }

        if ($this->requiresConfirmation($snapshot, 'tiempo') && $this->promisesTime($content)) {
            $violations[] = $this->violation(
                'PRODUCTION_TIME_REQUIRES_CONFIRMATION',
                'El contenido promete un plazo concreto pero el contexto requiere confirmarlo.',
                'warning: tiempo de produccion requiere confirmacion',
            );
        }

        if ($this->mentionsPromotion($content) && ! $this->hasPromotionSupport($snapshot)) {
            $violations[] = $this->violation(
                'UNSUPPORTED_PROMOTION',
                'El contenido anuncia una promocion que no esta respaldada por el contexto historico.',
                'no promotion support in historical snapshot',
            );
        }

        foreach ($this->futureIdeaTerms($snapshot) as $term) {
            if ($this->presentsAsAvailable($content, $term)) {
                $violations[] = $this->violation(
                    'FUTURE_IDEA_PRESENTED_AS_AVAILABLE',
                    'El contenido presenta una idea futura como disponible actualmente.',
                    'future_idea: '.$term,
                );
            }
        }

        return $violations === []
            ? EvaluationResult::passed()
            : new EvaluationResult('requires_review', $violations);
    }

    private function requiresConfirmation(ContextSnapshot $snapshot, string $subject): bool
    {
        $text = $this->normalize(implode("\n", [
            ...($snapshot->warnings ?? []),
            ...($snapshot->missing_information ?? []),
        ]));

        return str_contains($text, $subject)
            && (str_contains($text, 'requiere confirmacion')
                || str_contains($text, 'desconocid')
                || str_contains($text, 'no hay'));
    }

    private function mentionsPrice(string $content): bool
    {
        return preg_match('/(?:\$|ars\s*|usd\s*)\d{1,6}(?:[.\s]\d{3})*(?:,\d{1,2})?\b|\b(?:precio|vale|cuesta)\s*(?:de|:)?\s*\$?\s*\d+/iu', $content) === 1;
    }

    private function claimsStock(string $content): bool
    {
        return preg_match('/\b(?:hay|tenemos)\s+stock\b|\bdisponibles?(?:\s+ahora)?\b|\bultimas?\s+unidades\b|\bquedan\s+\d+\b/iu', $content) === 1;
    }

    private function promisesTime(string $content): bool
    {
        return preg_match('/\b(?:list[oa]s?|entrega|env[ií]o|producci[oó]n)\b.{0,24}\b(?:en|dentro de)\s+\d+\s*(?:horas?|d[ií]as?|semanas?)\b|\b(?:entrega|env[ií]o)\s+ma[ñn]ana\b|\ben el d[ií]a\b/iu', $content) === 1;
    }

    private function mentionsPromotion(string $content): bool
    {
        return preg_match('/\b\d{1,2}\s*%\s*off\b|\b\d+\s*x\s*1\b|\bdescuento\b|\bpromo(?:cion)?\b|\boferta\s+especial\b/iu', $content) === 1;
    }

    private function hasPromotionSupport(ContextSnapshot $snapshot): bool
    {
        $entries = collect($snapshot->relevant_knowledge ?? [])
            ->merge($snapshot->brand_context ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry)
                && in_array($entry['category'] ?? null, ['price', 'product'], true));

        return $entries->contains(fn (array $entry): bool => $this->mentionsPromotion((string) ($entry['title'] ?? '').' '.(string) ($entry['content'] ?? '')));
    }

    /**
     * @return array<int, string>
     */
    private function futureIdeaTerms(ContextSnapshot $snapshot): array
    {
        return collect($snapshot->relevant_knowledge ?? [])
            ->merge(collect($snapshot->matches ?? [])->pluck('entry'))
            ->filter(fn (mixed $entry): bool => is_array($entry) && ($entry['category'] ?? null) === 'future_idea')
            ->map(fn (array $entry): string => trim((string) preg_replace('/\b(?:idea|futura|futuro|proyecto)\b/iu', '', (string) ($entry['title'] ?? ''))))
            ->map(fn (string $term): string => trim((string) preg_replace('/[^\pL\pN]+/u', ' ', $term)))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4)
            ->unique()
            ->values()
            ->all();
    }

    private function presentsAsAvailable(string $content, string $term): bool
    {
        $term = preg_quote($term, '/');

        return preg_match('/(?:disponible|ofrecemos|ya pod[eé]s pedir|nuevo producto).{0,80}'.$term.'|'.$term.'.{0,80}(?:disponible|ofrecemos|ya pod[eé]s pedir|nuevo producto)/iu', $content) === 1;
    }

    /**
     * @return array{code: string, message: string, evidence: string}
     */
    private function violation(string $code, string $message, string $evidence): array
    {
        return compact('code', 'message', 'evidence');
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']));
    }
}
