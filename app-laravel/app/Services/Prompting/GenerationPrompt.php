<?php

namespace App\Services\Prompting;

final class GenerationPrompt
{
    /**
     * @param  array<int, string>  $systemInstructions
     * @param  array<int, string>  $outputRequirements
     * @param  array<int, array<string, mixed>>  $relevantKnowledge
     * @param  array<int, array<string, mixed>>  $brandContext
     * @param  array<int, array<string, mixed>>  $policies
     * @param  array<int, array<string, mixed>>  $restrictions
     * @param  array<int, array<string, mixed>>  $pendingKnowledge
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $missingInformation
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $request,
        public string $draftId,
        public string $brandId,
        public string $userId,
        public array $systemInstructions = [],
        public array $outputRequirements = [],
        public array $relevantKnowledge = [],
        public array $brandContext = [],
        public array $policies = [],
        public array $restrictions = [],
        public array $pendingKnowledge = [],
        public array $sources = [],
        public array $warnings = [],
        public array $missingInformation = [],
        public array $metadata = [],
    ) {}

    public function sections(): array
    {
        return [
            'systemInstructions' => $this->systemInstructions,
            'outputRequirements' => $this->outputRequirements,
            'userRequest' => $this->request,
            'brandContext' => $this->brandContext,
            'relevantKnowledge' => $this->relevantKnowledge,
            'pendingKnowledge' => $this->pendingKnowledge,
            'policies' => $this->policies,
            'restrictions' => $this->restrictions,
            'warnings' => $this->warnings,
            'missingInformation' => $this->missingInformation,
        ];
    }

    public function render(): string
    {
        return implode("\n\n", [$this->renderInstructions(), $this->renderInput()]);
    }

    public function renderInstructions(): string
    {
        return implode("\n\n", [
            'Instrucciones del sistema:',
            implode("\n", array_map(fn (string $instruction): string => '- '.$instruction, $this->systemInstructions)),
            'Requisitos de salida:',
            implode("\n", array_map(fn (string $requirement): string => '- '.$requirement, $this->outputRequirements)),
        ]);
    }

    public function renderInput(): string
    {
        $lines = [
            'Solicitud: '.$this->request,
            'Contexto de marca:',
        ];

        $lines[] = $this->formatEntries($this->brandContext, 'contexto de marca');
        $lines[] = 'Conocimiento relevante:';
        $lines[] = $this->formatEntries($this->relevantKnowledge, 'conocimiento relevante');

        if ($this->pendingKnowledge !== []) {
            $lines[] = 'Conocimiento pendiente/no confirmado:';
            $lines[] = $this->formatEntries($this->pendingKnowledge, 'conocimiento pendiente');
        }

        if ($this->policies !== []) {
            $lines[] = 'Políticas relevantes:';
            $lines[] = $this->formatEntries($this->policies, 'política');
        }

        if ($this->restrictions !== []) {
            $lines[] = 'Restricciones:';
            $lines[] = $this->formatEntries($this->restrictions, 'restricción');
        }

        if ($this->warnings !== []) {
            $lines[] = 'Advertencias:';
            $lines[] = implode("\n", array_map(fn (string $warning): string => '- '.$warning, $this->warnings));
        }

        if ($this->missingInformation !== []) {
            $lines[] = 'Información faltante:';
            $lines[] = implode("\n", array_map(fn (string $item): string => '- '.$item, $this->missingInformation));
        }

        if ($this->sources !== []) {
            $lines[] = 'Fuentes:';
            $lines[] = implode("\n", array_map(fn (string $source): string => '- '.$source, $this->sources));
        }

        return implode("\n\n", $lines);
    }

    private function formatEntries(array $entries, string $section): string
    {
        if ($entries === []) {
            return 'No hay '.$section.' disponible.';
        }

        $items = array_map(function (array $entry): string {
            $title = $entry['title'] ?? 'Elemento sin título';
            $content = $entry['content'] ?? '';
            $metadata = ($entry['status'] ?? null) === 'verified' && is_array($entry['grounding_metadata'] ?? null)
                ? $entry['grounding_metadata']
                : null;
            $structuredClaims = $metadata === null
                ? ''
                : "\n  Identificadores factuales estructurados: ".json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($content === '') {
                return '- '.$title.$structuredClaims;
            }

            return '- '.$title.': '.$content.$structuredClaims;
        }, $entries);

        return implode("\n", $items);
    }
}
