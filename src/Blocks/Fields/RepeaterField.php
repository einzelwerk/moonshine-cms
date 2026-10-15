<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * A list of repeated groups — slides, "label and value" pairs and the like:
 * structures without an identity of their own, so they live inside `content`
 * instead of becoming blocks.
 */
final class RepeaterField extends Field
{
    /** @var list<Field> */
    private array $fields = [];

    private ?int $min = null;

    private ?int $max = null;

    /**
     * @param  array<array-key, Field>  $fields  shape of one repeated item
     */
    public function fields(array $fields): static
    {
        $this->fields = array_values($fields);

        return $this;
    }

    public function min(int $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function normalize(mixed $raw): mixed
    {
        if (! is_array($raw)) {
            return null;
        }

        $items = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = [];

            foreach ($this->fields as $field) {
                if (array_key_exists($field->name(), $item)) {
                    $normalized[$field->name()] = $field->normalize($item[$field->name()]);
                }
            }

            $items[] = $normalized;
        }

        return $items;
    }

    public function rules(): array
    {
        $rules = ['array'];

        if ($this->min !== null) {
            $rules[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:'.$this->max;
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        if (! is_array($storedValue)) {
            return [];
        }

        $items = [];

        foreach ($storedValue as $item) {
            $source = is_array($item) ? $item : [];
            $payload = [];

            foreach ($this->fields as $field) {
                $payload[$field->name()] = $field->toPayload(
                    $source[$field->name()] ?? null,
                    $locale,
                    $fallbackLocale,
                    $linkResolver,
                );
            }

            $items[] = $payload;
        }

        return $items;
    }
}
