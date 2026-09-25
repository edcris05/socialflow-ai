<?php

namespace App\Services\Prompting;

use App\Models\ContextSnapshot;

class PromptComposer
{
    public function compose(ContextSnapshot $snapshot): GenerationPrompt
    {
        return new GenerationPrompt(
            request: $snapshot->query,
            draftId: (string) $snapshot->draft_id,
            brandId: (string) $snapshot->brand_id,
            userId: (string) $snapshot->user_id,
            relevantKnowledge: $snapshot->relevant_knowledge ?? [],
            brandContext: $snapshot->brand_context ?? [],
            policies: $snapshot->policies ?? [],
            restrictions: $snapshot->restrictions ?? [],
            pendingKnowledge: $snapshot->pending_knowledge ?? [],
            sources: $snapshot->sources ?? [],
            warnings: $snapshot->warnings ?? [],
            missingInformation: $snapshot->missing_information ?? [],
            metadata: [
                'brand_id' => $snapshot->brand_id,
                'draft_id' => $snapshot->draft_id,
                'sources' => $snapshot->sources ?? [],
                'warnings' => $snapshot->warnings ?? [],
                'missing_information' => $snapshot->missing_information ?? [],
                'matches' => $snapshot->matches ?? [],
            ],
        );
    }
}
