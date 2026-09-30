<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class GroundingMetadataCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $metadata = is_array($value)
            ? $value
            : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Grounding metadata must be an object.');
        }

        $this->validate($metadata);

        return $metadata;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Grounding metadata must be an array or null.');
        }

        $this->validate($value);

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $metadata */
    private function validate(array $metadata): void
    {
        $this->assertExactKeys($metadata, ['subject', 'claims', 'allowed_uses'], 'grounding metadata');
        $this->assertIdentifier($metadata['subject'], 'subject');

        if (! is_array($metadata['claims']) || ! array_is_list($metadata['claims']) || $metadata['claims'] === []) {
            throw new InvalidArgumentException('Grounding metadata claims must be a non-empty list.');
        }

        foreach ($metadata['claims'] as $index => $claim) {
            if (! is_array($claim)) {
                throw new InvalidArgumentException("Grounding claim {$index} must be an object.");
            }

            $this->assertExactKeys($claim, ['predicate', 'value', 'phrases', 'evidence_excerpt'], "grounding claim {$index}");
            $this->assertIdentifier($claim['predicate'], "grounding claim {$index} predicate");
            $this->assertIdentifier($claim['value'], "grounding claim {$index} value");
            $this->assertStringList($claim['phrases'], "grounding claim {$index} phrases", false);

            if (! is_string($claim['evidence_excerpt']) || trim($claim['evidence_excerpt']) === '') {
                throw new InvalidArgumentException("Grounding claim {$index} evidence_excerpt must be a non-empty string.");
            }
        }

        if (! is_array($metadata['allowed_uses'])) {
            throw new InvalidArgumentException('Grounding metadata allowed_uses must be an object.');
        }

        $allowedUses = $metadata['allowed_uses'];
        $this->assertExactKeys($allowedUses, ['values', 'coverage'], 'allowed_uses');
        $this->assertStringList($allowedUses['values'], 'allowed_uses values', true);

        if (! in_array($allowedUses['coverage'], ['open', 'closed'], true)) {
            throw new InvalidArgumentException('Grounding metadata coverage must be open or closed.');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $expected
     */
    private function assertExactKeys(array $value, array $expected, string $path): void
    {
        $keys = array_keys($value);
        sort($keys);
        sort($expected);

        if ($keys !== $expected) {
            throw new InvalidArgumentException("{$path} has an invalid structure.");
        }
    }

    private function assertIdentifier(mixed $value, string $path): void
    {
        if (! is_string($value) || preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("{$path} must be a stable snake_case identifier.");
        }
    }

    private function assertStringList(mixed $value, string $path, bool $allowEmpty): void
    {
        if (! is_array($value) || ! array_is_list($value) || (! $allowEmpty && $value === [])) {
            throw new InvalidArgumentException("{$path} must be a list".($allowEmpty ? '.' : ' with at least one item.'));
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("{$path} must contain only non-empty strings.");
            }
        }
    }
}
