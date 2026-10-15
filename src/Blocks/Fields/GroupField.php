<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * A nested group of fields, stored and returned as a nested object.
 */
final class GroupField extends Field
{
    /** @var list<Field> */
    private array $fields = [];

    /**
     * @param  array<array-key, Field>  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function normalize(mixed $raw): mixed
    {
        if (! is_array($raw)) {
            return null;
        }

        $normalized = [];

        foreach ($this->fields as $field) {
            if (array_key_exists($field->name(), $raw)) {
                $normalized[$field->name()] = $field->normalize($raw[$field->name()]);
            }
        }

        return $normalized;
    }

    public function rules(): array
    {
        return ['array'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        $source = is_array($storedValue) ? $storedValue : [];
        $payload = [];

        foreach ($this->fields as $field) {
            $payload[$field->name()] = $field->toPayload(
                $source[$field->name()] ?? null,
                $locale,
                $fallbackLocale,
                $linkResolver,
            );
        }

        return $payload;
    }
}
