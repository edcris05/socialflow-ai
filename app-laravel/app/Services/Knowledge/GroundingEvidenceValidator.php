<?php

namespace App\Services\Knowledge;

use InvalidArgumentException;

final class GroundingEvidenceValidator
{
    /** @param array<string, mixed>|null $metadata */
    public function validate(?array $metadata, string $content): void
    {
        if ($metadata === null) {
            return;
        }

        $claims = $metadata['claims'] ?? null;
        if (! is_array($claims)) {
            throw new InvalidArgumentException('Grounding metadata claims must be a list.');
        }

        $normalizedContent = $this->normalize($content);

        foreach ($claims as $index => $claim) {
            $excerpt = is_array($claim) ? ($claim['evidence_excerpt'] ?? null) : null;
            if (! is_string($excerpt) || trim($excerpt) === '') {
                throw new InvalidArgumentException("Grounding claim {$index} evidence_excerpt must be a non-empty string.");
            }

            if (! str_contains($normalizedContent, $this->normalize($excerpt))) {
                throw new InvalidArgumentException("La evidencia del claim {$index} no existe en el contenido de esta entrada.");
            }
        }
    }

    private function normalize(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));
        $value = preg_replace('/\s+/u', ' ', $value);

        if ($value === null) {
            throw new InvalidArgumentException('Grounding evidence must contain valid UTF-8 text.');
        }

        return mb_strtolower($value);
    }
}
