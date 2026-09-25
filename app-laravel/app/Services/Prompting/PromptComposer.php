<?php

namespace App\Services\Prompting;

use App\Models\ContextSnapshot;
use App\Services\Knowledge\ContextPackage;

class PromptComposer
{
    public function compose(ContextPackage|ContextSnapshot $context): GenerationPrompt
    {
        $package = $context instanceof ContextSnapshot ? $context->toPackage() : $context;

        return new GenerationPrompt(
            request: $package->query,
            draftId: $context instanceof ContextSnapshot ? (string) $context->draft_id : '',
            brandId: (string) $package->brand->getKey(),
            userId: $context instanceof ContextSnapshot ? (string) $context->user_id : '',
            systemInstructions: $this->guardrails(),
            relevantKnowledge: $this->entries($package->relevantKnowledge),
            brandContext: $this->entries($package->brandContext),
            policies: $this->entries($package->policies),
            restrictions: $this->entries($package->restrictions),
            pendingKnowledge: $this->entries($package->pendingKnowledge),
            sources: $package->sources->values()->all(),
            warnings: $package->warnings,
            missingInformation: $package->missingInformation,
            metadata: [
                'brand_id' => $package->brand->getKey(),
                'draft_id' => $context instanceof ContextSnapshot ? $context->draft_id : null,
                'sources' => $package->sources->values()->all(),
                'warnings' => $package->warnings,
                'missing_information' => $package->missingInformation,
                'matches' => $package->matches->values()->all(),
            ],
        );
    }

    private function entries(iterable $entries): array
    {
        return collect($entries)->map(function ($entry): array {
            return is_array($entry) ? $entry : $entry->only(['id', 'title', 'content', 'category', 'status', 'source', 'applicability']);
        })->values()->all();
    }

    private function guardrails(): array
    {
        return [
            'Sólo puede utilizar información proporcionada en el contexto de la marca y la solicitud del usuario.',
            'No inventes precios, promociones o descuentos, stock, tiempos de producción, métodos de pago, zonas o costos de entrega, ni horarios.',
            'No publiques información marcada como interna.',
            'Respeta siempre las políticas y restricciones recibidas.',
            'Trata la información faltante como desconocida y solicita confirmación cuando corresponda.',
            'pendingKnowledge es información no verificada: nunca la presentes como un hecho ni la utilices para afirmar disponibilidad, precios, stock, tiempos, condiciones comerciales u otros datos; si es necesaria para responder, trátala como información que requiere confirmación.',
            'No conviertas ideas futuras en productos o servicios disponibles.',
        ];
    }
}
