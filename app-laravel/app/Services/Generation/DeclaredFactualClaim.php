<?php

namespace App\Services\Generation;

use App\Services\Knowledge\Grounding\FactualClaim;
use InvalidArgumentException;

final readonly class DeclaredFactualClaim
{
    public function __construct(
        public string $subject,
        public string $predicate,
        public string $value,
        public string $text,
        public bool $textMatchesContent,
    ) {
        new FactualClaim($subject, $predicate, $value);

        if (trim($text) === '') {
            throw new InvalidArgumentException('Declared factual claim text cannot be empty.');
        }
    }

    public function toFactualClaim(): FactualClaim
    {
        return new FactualClaim($this->subject, $this->predicate, $this->value);
    }

    /** @return array{subject: string, predicate: string, value: string, text: string, text_matches_content: bool} */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'predicate' => $this->predicate,
            'value' => $this->value,
            'text' => $this->text,
            'text_matches_content' => $this->textMatchesContent,
        ];
    }
}
