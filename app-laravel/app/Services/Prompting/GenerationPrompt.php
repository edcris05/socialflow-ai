<?php

namespace App\Services\Prompting;

final class GenerationPrompt
{
    /**
     * @param array<int, array<string, mixed>> $relevantKnowledge
     * @param array<int, array<string, mixed>> $brandContext
     * @param array<int, array<string, mixed>> $policies
     * @param array<int, array<string, mixed>> $restrictions
     * @param array<int, array<string, mixed>> $pendingKnowledge
     * @param array<int, string> $sources
     * @param array<int, string> $warnings
     * @param array<int, string> $missingInformation
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $request,
        public string $draftId,
        public string $brandId,
        public string $userId,
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

    public function render(): string
    {
        $lines = [
            'Instrucción: Sólo puede utilizar información proporcionada en el contexto de la marca y la solicitud del usuario.',
            'Solicitud: '.$this->request,
            'Contexto de marca:',
        ];

        $lines[] = $this->formatEntries($this->brandContext, 'contexto de marca');
        $lines[] = 'Conocimiento relevante:';
        $lines[] = $this->formatEntries($this->relevantKnowledge, 'conocimiento relevante');

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

        $lines[] = 'No inventes información ni nombres de productos que no estén documentados en el contexto.';

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

            if ($content === '') {
                return '- '.$title;
            }

            return '- '.$title.': '.$content;
        }, $entries);

        return implode("\n", $items);
    }
}
